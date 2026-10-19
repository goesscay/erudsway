<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Enums\CouponKind;
use App\Modules\Commerce\Enums\SaleChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A discount code. Scope narrows from platform-wide, to one instructor's courses, to one course.
 *
 * @property int $id
 * @property string $code
 * @property CouponKind $kind
 * @property int $value percent (1–100) or paise
 * @property int|null $instructor_id
 * @property int|null $course_id
 * @property SaleChannel $channel
 * @property int|null $max_redemptions
 * @property int $per_user_limit
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Course|null $course
 */
class Coupon extends Model
{
    protected $fillable = ['code', 'kind', 'value', 'instructor_id', 'course_id', 'channel', 'max_redemptions', 'per_user_limit', 'starts_at', 'ends_at', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return [
            'kind' => CouponKind::class,
            'channel' => SaleChannel::class,
            'value' => 'integer',
            'max_redemptions' => 'integer',
            'per_user_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** The discount on one course at this list price, in paise. */
    public function discountFor(int $listPrice): int
    {
        return match ($this->kind) {
            CouponKind::Percent => intdiv($listPrice * min(100, $this->value), 100),
            CouponKind::Fixed => min($this->value, $listPrice),
            CouponKind::FixedPrice => max(0, $listPrice - $this->value),
        };
    }
}
