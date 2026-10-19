<?php

namespace App\Modules\Finance\Services;

use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Finance\Enums\LedgerAccount;
use App\Modules\Finance\Enums\LedgerStatus;
use App\Modules\Finance\Models\LedgerEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Writes the money ledger (PLAN §7). Every sale splits what the learner paid into the
 * instructor's share, the platform's share, GST and the gateway fee — the four always sum
 * to the amount paid. A refund writes the opposite; the gateway keeps its fee, so the
 * platform absorbs it.
 */
class Ledger
{
    public function recordSale(OrderItem $item, Carbon $paidAt): void
    {
        if ($item->amount === 0 || LedgerEntry::query()->where('order_item_id', $item->id)->whereNull('refund_id')->exists()) {
            return; // nothing was paid, or already recorded
        }

        $txn = (string) Str::uuid();
        $number = $item->order->number;
        $releaseAt = $paidAt->copy()->addDays((int) config('finance.hold_days'));

        $this->write($txn, $item, null, LedgerAccount::Instructor, $item->instructor_amount, "Sale {$number}: {$item->course_title}", LedgerStatus::Pending, $releaseAt);
        $this->write($txn, $item, null, LedgerAccount::Platform, $item->platform_amount, "Sale {$number}: platform share");
        $this->write($txn, $item, null, LedgerAccount::Tax, $item->tax, "Sale {$number}: GST collected");
        $this->write($txn, $item, null, LedgerAccount::GatewayFee, $item->gateway_fee, "Sale {$number}: gateway fee");
    }

    public function recordRefund(Refund $refund): void
    {
        if (LedgerEntry::query()->where('refund_id', $refund->id)->exists()) {
            return;
        }

        $item = $refund->orderItem;
        if ($item->amount === 0) {
            return;
        }

        // Full refunds reverse exactly; partial ones proportionally.
        $share = fn (int $value) => $refund->amount === $item->amount ? $value : intdiv($value * $refund->amount, $item->amount);
        $instructor = $share($item->instructor_amount);
        $tax = $share($item->tax);
        $platform = $refund->amount - $instructor - $tax;

        // If the sale's earnings are still on hold, the reversal waits with them, so the two
        // release together and net to zero. Otherwise it reduces the available balance now.
        $sale = LedgerEntry::query()->where('order_item_id', $item->id)->whereNull('refund_id')
            ->where('account', LedgerAccount::Instructor->value)->first();
        $onHold = $sale !== null && $sale->status === LedgerStatus::Pending;

        $txn = (string) Str::uuid();
        $number = $item->order->number;
        $this->write($txn, $item, $refund, LedgerAccount::Instructor, -$instructor, "Refund {$number}: {$item->course_title}",
            $onHold ? LedgerStatus::Pending : LedgerStatus::Available, $onHold ? $sale->available_at : now());
        $this->write($txn, $item, $refund, LedgerAccount::Platform, -$platform, "Refund {$number}: platform share (absorbs gateway fee)");
        $this->write($txn, $item, $refund, LedgerAccount::Tax, -$tax, "Refund {$number}: GST reversed");
    }

    /** Earnings whose hold has ended become available for payout. Returns how many were released. */
    public function release(?Carbon $at = null): int
    {
        return LedgerEntry::query()
            ->where('account', LedgerAccount::Instructor->value)
            ->where('status', LedgerStatus::Pending->value)
            ->where('available_at', '<=', $at ?? now())
            ->update(['status' => LedgerStatus::Available->value, 'updated_at' => now()]);
    }

    private function write(string $txn, OrderItem $item, ?Refund $refund, LedgerAccount $account, int $amount, string $description, LedgerStatus $status = LedgerStatus::Available, ?Carbon $availableAt = null): void
    {
        if ($amount === 0) {
            return;
        }

        LedgerEntry::query()->create([
            'txn_uuid' => $txn,
            'account' => $account,
            'instructor_id' => $account === LedgerAccount::Instructor ? $item->instructor_id : null,
            'amount' => $amount,
            'currency' => $item->order->currency,
            'description' => mb_substr($description, 0, 200),
            'order_item_id' => $item->id,
            'refund_id' => $refund?->id,
            'status' => $status,
            'available_at' => $availableAt ?? now(),
        ]);
    }
}
