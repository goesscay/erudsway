<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Enums\SaleChannel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One course in an order, with prices, GST and commission frozen at the time of sale.
 *
 * @property int $id
 * @property int $order_id
 * @property int $course_id
 * @property int $instructor_id
 * @property string $course_title
 * @property int $list_price
 * @property int $discount
 * @property int $amount
 * @property int $tax
 * @property int $net_amount
 * @property int|null $coupon_id
 * @property SaleChannel $channel
 * @property int $commission_rate_bps
 * @property int $gateway_fee
 * @property int $instructor_amount
 * @property int $platform_amount
 * @property int $refunded_amount
 * @property-read Order $order
 * @property-read Course $course
 * @property-read Collection<int, Refund> $refunds
 */
class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => SaleChannel::class,
            'list_price' => 'integer',
            'discount' => 'integer',
            'amount' => 'integer',
            'tax' => 'integer',
            'net_amount' => 'integer',
            'commission_rate_bps' => 'integer',
            'gateway_fee' => 'integer',
            'instructor_amount' => 'integer',
            'platform_amount' => 'integer',
            'refunded_amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
