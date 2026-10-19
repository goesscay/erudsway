<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommissionRule */
class CommissionRuleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** @var 'organic'|'instructor_referral'|'platform_promoted' */
            'channel' => $this->channel->value,
            'instructor' => $this->instructor ? ['id' => $this->instructor->uuid, 'name' => $this->instructor->name] : null,
            'instructor_percent' => $this->instructor_bps / 100,
            'effective_from' => $this->effective_from->toIso8601String(),
            'effective_to' => $this->effective_to?->toIso8601String(),
            'active' => $this->effective_from->isPast() && ($this->effective_to === null || $this->effective_to->isFuture()),
        ];
    }
}
