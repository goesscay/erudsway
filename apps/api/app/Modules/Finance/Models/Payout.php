<?php

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Finance\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A transfer to one instructor, built from their available ledger entries.
 *
 * @property int $id
 * @property string $uuid
 * @property int $instructor_id
 * @property Carbon $period_end
 * @property int $gross
 * @property int $tds
 * @property string $tds_section
 * @property int $tds_rate_bps
 * @property int $net
 * @property string $currency
 * @property PayoutStatus $status
 * @property string $method
 * @property array{account_holder: string, account_last4: string, ifsc: string, pan_last4: string} $bank_snapshot
 * @property string|null $reference
 * @property string|null $note
 * @property int|null $generated_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $paid_by
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 * @property-read User $instructor
 * @property-read Collection<int, LedgerEntry> $entries
 */
class Payout extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'period_end' => 'date',
            'gross' => 'integer',
            'tds' => 'integer',
            'net' => 'integer',
            'tds_rate_bps' => 'integer',
            'bank_snapshot' => 'array',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
