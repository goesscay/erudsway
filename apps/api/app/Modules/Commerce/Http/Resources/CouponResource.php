<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Coupon */
class CouponResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            /** @var 'percent'|'fixed'|'fixed_price' */
            'kind' => $this->kind->value,
            'value' => $this->value,
            /** @var 'organic'|'instructor_referral'|'platform_promoted' */
            'channel' => $this->channel->value,
            'course' => $this->course ? ['slug' => $this->course->slug, 'title' => $this->course->title] : null,
            'max_redemptions' => $this->max_redemptions,
            'per_user_limit' => $this->per_user_limit,
            'redemptions' => (int) ($this->getAttribute('redemptions_count') ?? 0),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
