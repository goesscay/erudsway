<?php

namespace App\Modules\Finance\Services;

use App\Models\User;
use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Finance\Models\LedgerEntry;
use Illuminate\Support\Carbon;

/** Period figures for the finance team, all derived from orders, refunds and the ledger. */
class Reports
{
    public function __construct(private readonly Balances $balances) {}

    /**
     * @return array{
     *     from: string, to: string,
     *     sales: array{count: int, list_value: int, discounts: int, collected: int},
     *     refunds: array{count: int, amount: int},
     *     split: array{instructor: int, platform: int, tax: int, gateway_fee: int},
     *     instructors: list<array{id: string, name: string, sales: int, collected: int, earnings: int, pending: int, available: int}>
     * }
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereBetween('paid_at', [$from, $to]))
            ->get(['id', 'instructor_id', 'list_price', 'discount', 'amount']);

        $refunds = Refund::query()->where('status', RefundStatus::Processed->value)->whereBetween('processed_at', [$from, $to])->get(['amount']);

        // Net of refunds booked in the period, per ledger account.
        $split = LedgerEntry::query()->whereBetween('created_at', [$from, $to])
            ->selectRaw('account, SUM(amount) as total')->groupBy('account')->pluck('total', 'account');

        $earnings = LedgerEntry::query()->where('account', 'instructor')->whereBetween('created_at', [$from, $to])
            ->selectRaw('instructor_id, SUM(amount) as total')->groupBy('instructor_id')->pluck('total', 'instructor_id');

        $instructorIds = $items->pluck('instructor_id')->merge($earnings->keys())->unique()->values();
        $users = User::query()->whereIn('id', $instructorIds)->get(['id', 'uuid', 'name'])->keyBy('id');

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'sales' => [
                'count' => $items->count(),
                'list_value' => (int) $items->sum('list_price'),
                'discounts' => (int) $items->sum('discount'),
                'collected' => (int) $items->sum('amount'),
            ],
            'refunds' => ['count' => $refunds->count(), 'amount' => (int) $refunds->sum('amount')],
            'split' => [
                'instructor' => (int) ($split['instructor'] ?? 0),
                'platform' => (int) ($split['platform'] ?? 0),
                'tax' => (int) ($split['tax'] ?? 0),
                'gateway_fee' => (int) ($split['gateway_fee'] ?? 0),
            ],
            'instructors' => $instructorIds->map(function ($id) use ($items, $earnings, $users) {
                $mine = $items->where('instructor_id', $id);
                $balance = $this->balances->for((int) $id);

                return [
                    'id' => (string) $users->get($id)?->uuid,
                    'name' => (string) $users->get($id)?->name,
                    'sales' => $mine->count(),
                    'collected' => (int) $mine->sum('amount'),
                    'earnings' => (int) ($earnings[$id] ?? 0),
                    'pending' => $balance['pending'],
                    'available' => $balance['available'],
                ];
            })->sortByDesc('collected')->values()->all(),
        ];
    }
}
