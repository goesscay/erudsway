<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $windowEnds = $this->paid_at?->copy()->addDays((int) config('commerce.refunds.window_days'));

        return [
            'id' => $this->uuid,
            'number' => $this->number,
            /** @var 'pending'|'paid'|'failed'|'cancelled'|'partially_refunded'|'refunded' */
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'coupon_code' => $this->coupon_code,
            'created_at' => $this->created_at->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'refund_window_ends_at' => $windowEnds?->toIso8601String(),
            'billing' => $this->billing_snapshot,
            /** @var list<array{id: int, course: array{slug: string, title: string, thumbnail_url: string|null}, list_price: int, discount: int, amount: int, refund: array{status: 'requested'|'rejected'|'processing'|'processed'|'failed', amount: int, decision_note: string|null}|null, refundable: bool}> */
            'items' => $this->items->map(function (OrderItem $item) use ($windowEnds) {
                /** @var Refund|null $refund */
                $refund = $item->refunds->sortByDesc('id')->first();
                $open = $refund !== null && $refund->status !== RefundStatus::Rejected && $refund->status !== RefundStatus::Failed;

                return [
                    'id' => $item->id,
                    'course' => ['slug' => $item->course->slug, 'title' => $item->course_title, 'thumbnail_url' => $item->course->thumbnailUrl()],
                    'list_price' => $item->list_price,
                    'discount' => $item->discount,
                    'amount' => $item->amount,
                    'refund' => $refund ? ['status' => $refund->status->value, 'amount' => $refund->amount, 'decision_note' => $refund->decision_note] : null,
                    'refundable' => $this->isPaid() && ! $open && $windowEnds !== null && $windowEnds->isFuture(),
                ];
            })->values()->all(),
            'invoice' => $this->isPaid() && $this->total > 0
                ? ['number' => $this->invoice->number ?? null, 'download_url' => url("/api/v1/orders/{$this->uuid}/invoice")]
                : null,
        ];
    }
}
