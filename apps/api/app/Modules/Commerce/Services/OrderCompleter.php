<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Jobs\IssueInvoice;
use App\Modules\Commerce\Models\CouponRedemption;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Commerce\Notifications\OrderReceipt;
use App\Modules\Commerce\Payments\GatewayPayment;
use App\Modules\Learning\Services\Enroller;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Marks an order paid and grants access. Called by the browser's verify step, the gateway
 * webhook and reconciliation — whichever arrives first wins; the rest are no-ops thanks to
 * the row lock and the unique payment id.
 */
class OrderCompleter
{
    public function __construct(
        private readonly Enroller $enroller,
        private readonly CartService $carts,
        private readonly AuditLogger $audit,
    ) {}

    public function complete(Order $order, GatewayPayment $payment): Order
    {
        $completed = DB::transaction(function () use ($order, $payment) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->isPaid()) {
                return null;
            }

            if (! $payment->isCaptured() || $payment->orderId !== $locked->gateway_order_id || $payment->amount !== $locked->total) {
                return false;
            }

            Payment::query()->firstOrCreate(['gateway_payment_id' => $payment->id], [
                'order_id' => $locked->id,
                'gateway' => $locked->gateway,
                'status' => 'captured',
                'amount' => $payment->amount,
                'fee' => $payment->fee,
                'tax_on_fee' => $payment->tax,
                'method' => $payment->method,
                'raw' => $payment->raw,
                'captured_at' => now(),
            ]);

            $this->settleItems($locked, $payment->fee);
            $this->markPaid($locked);

            return $locked;
        });

        if ($completed === false) {
            // Recorded outside the transaction so the trail survives.
            $this->audit->record('payment.mismatch', $order, null, [
                'payment_id' => $payment->id, 'status' => $payment->status, 'amount' => $payment->amount, 'gateway_order_id' => $payment->orderId,
            ]);
            throw new DomainException('PAYMENT_MISMATCH', 'We couldn’t confirm this payment. If you were charged, our team will apply or refund it.', 409);
        }
        if ($completed !== null) {
            IssueInvoice::dispatch($completed->id);
        }

        return $order->refresh();
    }

    /** Fully discounted orders: nothing to charge, no invoice. */
    public function completeWithoutPayment(Order $order): void
    {
        $paid = DB::transaction(function () use ($order) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->isPaid() || $locked->total !== 0) {
                return null;
            }
            $this->settleItems($locked, 0);
            $this->markPaid($locked);

            return $locked;
        });

        $paid?->user->notify(new OrderReceipt($paid));
    }

    /**
     * Commission (decision D3): the instructor's share is of net revenue — the taxable value
     * less this item's share of the gateway fee. The platform keeps the rest of the net.
     */
    private function settleItems(Order $order, int $fee): void
    {
        $items = $order->items()->get();
        $total = max(1, $items->sum('amount'));
        $allocated = 0;

        foreach ($items->values() as $i => $item) {
            /** @var OrderItem $item */
            $share = $i === $items->count() - 1 ? $fee - $allocated : intdiv($fee * $item->amount, $total);
            $allocated += $share;
            $base = max(0, $item->net_amount - $share);
            $instructor = intdiv($base * $item->commission_rate_bps, 10000);

            $item->forceFill([
                'gateway_fee' => $share,
                'instructor_amount' => $instructor,
                'platform_amount' => $base - $instructor,
            ])->save();
        }
    }

    private function markPaid(Order $order): void
    {
        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();
        $order->load('items.course', 'user');

        foreach ($order->items as $item) {
            $this->enroller->enrollPurchase($order->user, $item->course, $item->id);
            if ($item->coupon_id !== null) {
                CouponRedemption::query()->firstOrCreate(['coupon_id' => $item->coupon_id, 'order_id' => $order->id], ['user_id' => $order->user_id]);
            }
        }

        $this->carts->forget($order->user, $order->items->pluck('course_id'));
        OrderPaid::dispatch($order);
        $this->audit->record('order.paid', $order, null, ['total' => $order->total, 'number' => $order->number], $order->user);
    }
}
