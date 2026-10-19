<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * GST tax invoice for a paid order. Seller and buyer details are frozen at issue time.
 *
 * @property int $id
 * @property int $order_id
 * @property string $number
 * @property string $financial_year
 * @property string|null $file_path
 * @property array<string, string|null> $seller
 * @property array<string, string|null> $buyer
 * @property Carbon $issued_at
 * @property-read Order $order
 */
class Invoice extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'seller' => 'array',
            'buyer' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
