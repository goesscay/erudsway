<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payout */
class PayoutResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            /** @var 'draft'|'approved'|'paid'|'cancelled' */
            'status' => $this->status->value,
            'period_end' => $this->period_end->toDateString(),
            'gross' => $this->gross,
            'tds' => $this->tds,
            'tds_section' => $this->tds_section,
            'tds_rate_bps' => $this->tds_rate_bps,
            'net' => $this->net,
            'currency' => $this->currency,
            'bank' => [
                'account_holder' => $this->bank_snapshot['account_holder'],
                'account_last4' => $this->bank_snapshot['account_last4'],
                'ifsc' => $this->bank_snapshot['ifsc'],
            ],
            'reference' => $this->reference,
            'note' => $this->note,
            'entries_count' => (int) ($this->getAttribute('entries_count') ?? 0),
            'instructor' => ['id' => $this->instructor->uuid, 'name' => $this->instructor->name, 'email' => $this->instructor->email],
            'generated_by_me' => $request->user()?->getAuthIdentifier() === $this->generated_by,
            'created_at' => $this->created_at->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
