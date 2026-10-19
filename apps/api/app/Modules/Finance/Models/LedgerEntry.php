<?php

namespace App\Modules\Finance\Models;

use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Finance\Enums\LedgerAccount;
use App\Modules\Finance\Enums\LedgerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of the money ledger. Insert-only: only `status`, `available_at` and `payout_id`
 * ever change after creation (the database rejects anything else).
 *
 * @property int $id
 * @property string $txn_uuid
 * @property LedgerAccount $account
 * @property int|null $instructor_id
 * @property int $amount signed paise
 * @property string $currency
 * @property string $description
 * @property int|null $order_item_id
 * @property int|null $refund_id
 * @property int|null $payout_id
 * @property LedgerStatus $status
 * @property Carbon|null $available_at
 * @property Carbon $created_at
 * @property-read OrderItem|null $orderItem
 */
class LedgerEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'account' => LedgerAccount::class,
            'status' => LedgerStatus::class,
            'amount' => 'integer',
            'available_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Ledger entries are never deleted.'));
        static::updating(function (self $entry) {
            if ($entry->isDirty(['amount', 'account', 'instructor_id', 'txn_uuid', 'order_item_id', 'refund_id'])) {
                throw new LogicException('Ledger amounts are never edited; record a correcting entry.');
            }
        });
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @param  Builder<LedgerEntry>  $query
     * @return Builder<LedgerEntry>
     */
    public function scopeForInstructor(Builder $query, int $instructorId): Builder
    {
        return $query->where('account', LedgerAccount::Instructor->value)->where('instructor_id', $instructorId);
    }
}
