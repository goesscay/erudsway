<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Commerce\Payments\PaymentGateway;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Compares captured gateway payments with our orders (PLAN §7). A captured payment for a
 * pending order means both the browser and the webhook were lost: the order is completed.
 * Payments we can't match, and paid orders the gateway doesn't know, are flagged in the
 * audit log for the finance team.
 */
class Reconciler
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderCompleter $completer,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{checked: int, completed: int, unmatched: int, missing: int} */
    public function run(Carbon $from, Carbon $to): array
    {
        $summary = ['checked' => 0, 'completed' => 0, 'unmatched' => 0, 'missing' => 0];
        $seen = [];

        foreach ($this->gateway->capturedPayments($from, $to) as $payment) {
            $summary['checked']++;
            $seen[$payment->id] = true;
            if (Payment::query()->where('gateway_payment_id', $payment->id)->exists()) {
                continue;
            }

            $order = $payment->orderId ? Order::query()->where('gateway_order_id', $payment->orderId)->first() : null;
            if ($order !== null && $order->status === OrderStatus::Pending) {
                try {
                    $this->completer->complete($order, $payment);
                    $summary['completed']++;
                    $this->audit->record('payment.reconciled', $order, null, ['payment_id' => $payment->id]);

                    continue;
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $summary['unmatched']++;
            $this->audit->record('payment.unmatched', $order, null, ['payment_id' => $payment->id, 'gateway_order_id' => $payment->orderId, 'amount' => $payment->amount]);
        }

        $paid = Payment::query()
            ->where('gateway', $this->gateway->name())
            ->whereBetween('captured_at', [$from, $to])
            ->get();
        foreach ($paid as $row) {
            if (! isset($seen[$row->gateway_payment_id])) {
                $summary['missing']++;
                $this->audit->record('payment.missing_at_gateway', $row->order, null, ['payment_id' => $row->gateway_payment_id]);
            }
        }

        return $summary;
    }
}
