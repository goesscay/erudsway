<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A captured gateway payment for an order.
 *
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string $gateway_payment_id
 * @property string $status
 * @property int $amount
 * @property int $fee
 * @property int $tax_on_fee
 * @property string|null $method
 * @property Carbon|null $captured_at
 * @property-read Order $order
 */
class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'amount' => 'integer',
            'fee' => 'integer',
            'tax_on_fee' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
