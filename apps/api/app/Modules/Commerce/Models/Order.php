<?php

namespace App\Modules\Commerce\Models;

use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $number
 * @property int $user_id
 * @property OrderStatus $status
 * @property string $currency
 * @property int $subtotal
 * @property int $discount_total
 * @property int $tax_total
 * @property int $total
 * @property string|null $coupon_code
 * @property string $gateway
 * @property string|null $gateway_order_id
 * @property Carbon|null $paid_at
 * @property array{name: string, email: string, state_code: string, state: string, gstin: string|null, business_name: string|null, address: string|null} $billing_snapshot
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read Invoice|null $invoice
 */
class Order extends Model
{
    use HasUuids;

    protected $fillable = ['number', 'user_id', 'status', 'currency', 'subtotal', 'discount_total', 'tax_total', 'total', 'coupon_code', 'gateway', 'billing_snapshot', 'ip', 'user_agent'];

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
            'status' => OrderStatus::class,
            'billing_snapshot' => 'array',
            'paid_at' => 'datetime',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded, OrderStatus::Refunded], true);
    }
}
