<?php

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Commerce\Enums\SaleChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The instructor's share for a sale channel, platform-wide or for one instructor, over a
 * period. Rules are never edited: a new rule ends the one it replaces.
 *
 * @property int $id
 * @property SaleChannel $channel
 * @property int|null $instructor_id
 * @property int $instructor_bps
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read User|null $instructor
 */
class CommissionRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => SaleChannel::class,
            'instructor_bps' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * @param  Builder<CommissionRule>  $query
     * @return Builder<CommissionRule>
     */
    public function scopeActiveAt(Builder $query, Carbon $at): Builder
    {
        return $query->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at));
    }
}
