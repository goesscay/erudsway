<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Events\RefundProcessed;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Commerce\Notifications\RefundUpdated;
use App\Modules\Commerce\Payments\GatewayException;
use App\Modules\Commerce\Payments\PaymentGateway;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Services\Enroller;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Refund policy (decision D4): within 30 days of purchase; approved automatically when less
 * than 30% of the course is complete, otherwise reviewed by staff. Access ends when the
 * refund is issued; the gateway confirms completion by webhook.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Enroller $enroller,
        private readonly AuditLogger $audit,
    ) {}

    public function request(User $user, OrderItem $item, string $reason): Refund
    {
        $order = $item->order;
        if ($order->user_id !== $user->id) {
            throw new DomainException('NOT_FOUND', 'Order not found.', 404);
        }
        $this->assertRefundable($item);

        $enrollment = Enrollment::query()->where('user_id', $user->id)->where('course_id', $item->course_id)->first();
        $auto = $enrollment !== null && $enrollment->progress_percent < (int) config('commerce.refunds.auto_approve_below_percent');

        $refund = Refund::query()->create([
            'order_item_id' => $item->id,
            'amount' => $item->amount - $item->refunded_amount,
            'status' => RefundStatus::Requested,
            'reason' => $reason,
            'requested_by' => $user->id,
        ]);
        $this->audit->record('refund.requested', $refund, null, ['order' => $order->number, 'course_id' => $item->course_id, 'auto' => $auto], $user);

        if ($auto) {
            $refund->forceFill(['auto_approved' => true])->save();

            return $this->issue($refund, null);
        }

        $user->notify(new RefundUpdated($refund));

        return $refund;
    }

    public function approve(Refund $refund, User $staff, ?string $note): Refund
    {
        if (! in_array($refund->status, [RefundStatus::Requested, RefundStatus::Failed], true)) {
            throw new DomainException('REFUND_CLOSED', 'This refund has already been decided.', 409);
        }
        $refund->forceFill(['decision_note' => $note])->save();

        return $this->issue($refund, $staff);
    }

    public function reject(Refund $refund, User $staff, string $note): Refund
    {
        if ($refund->status !== RefundStatus::Requested) {
            throw new DomainException('REFUND_CLOSED', 'This refund has already been decided.', 409);
        }
        $refund->forceFill(['status' => RefundStatus::Rejected, 'approved_by' => $staff->id, 'decision_note' => $note])->save();
        $this->audit->record('refund.rejected', $refund, null, ['note' => $note], $staff);
        $refund->requester->notify(new RefundUpdated($refund));

        return $refund;
    }

    /** Gateway confirmed (webhook): money has left. */
    public function markProcessed(Refund $refund): void
    {
        DB::transaction(function () use ($refund) {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status === RefundStatus::Processed) {
                return;
            }
            $locked->forceFill(['status' => RefundStatus::Processed, 'processed_at' => now()])->save();
            $item = OrderItem::query()->lockForUpdate()->findOrFail($locked->order_item_id);
            $item->forceFill(['refunded_amount' => min($item->amount, $item->refunded_amount + $locked->amount)])->save();
            $this->syncOrderStatus($item->order);
            RefundProcessed::dispatch($locked);
        });

        $refund->refresh()->requester->notify(new RefundUpdated($refund));
    }

    public function markFailed(Refund $refund, string $why): void
    {
        $refund->forceFill(['status' => RefundStatus::Failed, 'decision_note' => trim(($refund->decision_note ?? '')."\nGateway: {$why}")])->save();
        $this->audit->record('refund.failed', $refund, null, ['error' => $why]);
    }

    private function issue(Refund $refund, ?User $staff): Refund
    {
        $item = $refund->orderItem;
        $order = $item->order;
        $payment = Payment::query()->where('order_id', $order->id)->where('status', 'captured')->latest('id')->first();

        $refund->forceFill(['approved_by' => $staff?->id, 'payment_id' => $payment?->id, 'status' => RefundStatus::Processing])->save();
        $this->enroller->markRefunded($order->user, $item->course);
        $this->audit->record('refund.approved', $refund, null, ['auto' => $refund->auto_approved, 'amount' => $refund->amount], $staff);

        if ($payment === null) {
            // Nothing was charged (fully discounted order): access simply ends.
            $this->markProcessed($refund);

            return $refund->refresh();
        }

        try {
            $result = $this->gateway->refund($payment->gateway_payment_id, $refund->amount, ['order' => $order->number, 'refund_id' => (string) $refund->id]);
            $refund->forceFill(['gateway_refund_id' => $result->id])->save();
            if ($result->status === 'processed') {
                $this->markProcessed($refund);
            } elseif ($result->status === 'failed') {
                $this->markFailed($refund, 'refund failed at the gateway');
            }
        } catch (GatewayException $e) {
            report($e);
            $this->markFailed($refund, $e->getMessage());
        }

        $refund->refresh()->requester->notify(new RefundUpdated($refund));

        return $refund;
    }

    private function assertRefundable(OrderItem $item): void
    {
        $order = $item->order;
        if (! $order->isPaid() || $order->paid_at === null) {
            throw new DomainException('NOT_REFUNDABLE', 'This order hasn’t been paid.', 422);
        }
        if ($order->paid_at->lt(now()->subDays((int) config('commerce.refunds.window_days')))) {
            throw new DomainException('REFUND_WINDOW_CLOSED', 'Refunds are available within 30 days of purchase.', 422);
        }
        if ($item->refunded_amount >= $item->amount && $item->amount > 0) {
            throw new DomainException('ALREADY_REFUNDED', 'This course has already been refunded.', 409);
        }
        $open = $item->refunds()->whereIn('status', [RefundStatus::Requested, RefundStatus::Processing, RefundStatus::Processed])->exists();
        if ($open) {
            throw new DomainException('REFUND_EXISTS', 'A refund for this course is already in progress.', 409);
        }
    }

    private function syncOrderStatus(Order $order): void
    {
        $items = $order->items()->withExists(['refunds as fully_refunded' => fn ($q) => $q->where('status', RefundStatus::Processed)])->get();
        $refunded = $items->filter(fn (OrderItem $i) => (bool) $i->getAttribute('fully_refunded'))->count();
        $status = $refunded === 0 ? OrderStatus::Paid : ($refunded === $items->count() ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded);
        $order->forceFill(['status' => $status])->save();
    }
}
