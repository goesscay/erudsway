<?php

namespace App\Modules\Finance\Http\Resources;

use App\Modules\Finance\Models\PayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payout details, masked. Full PAN and account number never leave the server.
 *
 * @mixin PayoutAccount
 */
class PayoutAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /** @var 'submitted'|'verified'|'rejected' */
            'status' => $this->status->value,
            'legal_name' => $this->legal_name,
            'pan_masked' => 'XXXXXX'.$this->pan_last4,
            'gstin' => $this->gstin,
            'account_holder' => $this->account_holder,
            'account_masked' => 'XXXX'.$this->account_last4,
            'ifsc' => $this->ifsc,
            'review_note' => $this->review_note,
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'instructor' => ['id' => $this->user->uuid, 'name' => $this->user->name, 'email' => $this->user->email],
        ];
    }
}
