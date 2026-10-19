<?php

namespace App\Modules\Authoring\Http\Resources;

use App\Modules\Catalog\Models\PriceTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PriceTier
 */
class PriceTierResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
