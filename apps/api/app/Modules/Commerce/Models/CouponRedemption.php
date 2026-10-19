<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $coupon_id
 * @property int $user_id
 * @property int $order_id
 */
class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'order_id'];
}
