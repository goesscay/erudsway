<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Commerce\Models\WebhookEvent;
use App\Modules\Commerce\Payments\GatewayPayment;
use App\Modules\Commerce\Services\OrderCompleter;
use App\Modules\Commerce\Services\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Acts on a stored Razorpay webhook (queue "critical"). Handlers are idempotent, so a
 * retry or a redelivered event changes nothing twice.
 */
class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('critical');
    }

    public function handle(OrderCompleter $completer, RefundService $refunds): void
    {
        $event = WebhookEvent::query()->findOrFail($this->eventId);
        if ($event->status === 'processed') {
            return;
        }
        $event->increment('attempts');

        try {
            $handled = match ($event->type) {
                'payment.captured', 'order.paid' => $this->paymentCaptured($event, $completer),
                'refund.processed' => $this->refund($event, fn (Refund $r) => $refunds->markProcessed($r)),
                'refund.failed' => $this->refund($event, fn (Refund $r) => $refunds->markFailed($r, 'refund.failed webhook')),
                default => false,
            };
            $event->forceFill(['status' => $handled ? 'processed' : 'ignored', 'processed_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $e) {
            $event->forceFill(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 2000)])->save();
            throw $e;
        }
    }

    private function paymentCaptured(WebhookEvent $event, OrderCompleter $completer): bool
    {
        $entity = data_get($event->payload, 'payload.payment.entity');
        if (! is_array($entity) || ($entity['status'] ?? null) !== 'captured') {
            return false;
        }
        $order = Order::query()->where('gateway_order_id', $entity['order_id'] ?? null)->first();
        if ($order === null) {
            return false; // Not ours (e.g. the old site on the same account); reconciliation reports it.
        }
        $completer->complete($order, GatewayPayment::fromRazorpay($entity));

        return true;
    }

    /** @param callable(Refund): void $apply */
    private function refund(WebhookEvent $event, callable $apply): bool
    {
        $refund = Refund::query()->where('gateway_refund_id', data_get($event->payload, 'payload.refund.entity.id'))->first();
        if ($refund === null) {
            return false;
        }
        $apply($refund);

        return true;
    }
}
