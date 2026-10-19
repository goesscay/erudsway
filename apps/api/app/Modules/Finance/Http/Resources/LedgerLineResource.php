<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of an instructor's statement.
 *
 * @mixin LedgerEntry
 */
class LedgerLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            /** @var 'pending'|'available'|'paid' */
            'status' => $this->status->value,
            'kind' => $this->refund_id !== null ? 'refund' : 'sale',
            'available_at' => $this->available_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
