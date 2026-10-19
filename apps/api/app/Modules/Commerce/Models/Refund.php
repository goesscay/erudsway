<?php

namespace App\Modules\Commerce\Models;

use App\Models\User;
use App\Modules\Commerce\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_item_id
 * @property int|null $payment_id
 * @property int $amount
 * @property string|null $gateway_refund_id
 * @property RefundStatus $status
 * @property string $reason
 * @property int $requested_by
 * @property int|null $approved_by
 * @property bool $auto_approved
 * @property string|null $decision_note
 * @property Carbon|null $processed_at
 * @property Carbon $created_at
 * @property-read OrderItem $orderItem
 * @property-read Payment|null $payment
 * @property-read User $requester
 */
class Refund extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'integer',
            'auto_approved' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
