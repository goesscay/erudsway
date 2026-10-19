<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\LedgerStatus;
use App\Modules\Finance\Models\LedgerEntry;

/** An instructor's earnings by state, read straight from the ledger. */
class Balances
{
    /** @return array{pending: int, available: int, in_payout: int, paid: int, lifetime: int} */
    public function for(int $instructorId): array
    {
        $rows = LedgerEntry::query()->forInstructor($instructorId)
            ->selectRaw('status, payout_id IS NOT NULL as in_payout, SUM(amount) as total')
            ->groupBy('status')
            ->groupByRaw('payout_id IS NOT NULL')
            ->get();

        $sum = fn (LedgerStatus $status, ?bool $inPayout = null) => (int) $rows
            ->filter(fn ($r) => $r->getAttribute('status') === $status && ($inPayout === null || (bool) $r->getAttribute('in_payout') === $inPayout))
            ->sum(fn ($r) => (int) $r->getAttribute('total'));

        return [
            'pending' => $sum(LedgerStatus::Pending),
            'available' => $sum(LedgerStatus::Available, false),
            'in_payout' => $sum(LedgerStatus::Available, true),
            'paid' => $sum(LedgerStatus::Paid),
            'lifetime' => (int) $rows->sum(fn ($r) => (int) $r->getAttribute('total')),
        ];
    }
}
