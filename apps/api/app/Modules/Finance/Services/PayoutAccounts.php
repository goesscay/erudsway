<?php

namespace App\Modules\Finance\Services;

use App\Models\User;
use App\Modules\Finance\Enums\PayoutAccountStatus;
use App\Modules\Finance\Models\PayoutAccount;
use App\Modules\Finance\Notifications\PayoutAccountReviewed;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;

/** KYC and bank details. Any change to them needs fresh verification by finance staff. */
class PayoutAccounts
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{legal_name: string, pan: string, gstin?: string|null, account_holder: string, account_number: string, ifsc: string}  $data
     */
    public function save(User $instructor, array $data): PayoutAccount
    {
        $account = PayoutAccount::query()->find($instructor->id) ?? new PayoutAccount(['user_id' => $instructor->id]);
        $changed = ! $account->exists
            || $account->pan !== $data['pan']
            || $account->account_number !== $data['account_number']
            || $account->ifsc !== $data['ifsc']
            || $account->legal_name !== $data['legal_name']
            || $account->account_holder !== $data['account_holder']
            || $account->gstin !== ($data['gstin'] ?? null);

        $account->forceFill([
            'legal_name' => $data['legal_name'],
            'pan' => $data['pan'],
            'pan_last4' => substr($data['pan'], -4),
            'gstin' => $data['gstin'] ?? null,
            'account_holder' => $data['account_holder'],
            'account_number' => $data['account_number'],
            'account_last4' => substr($data['account_number'], -4),
            'ifsc' => $data['ifsc'],
        ]);

        if ($changed) {
            // Bank changes are the classic payout fraud: always re-verify.
            $account->forceFill(['status' => PayoutAccountStatus::Submitted, 'submitted_at' => now(), 'verified_at' => null, 'verified_by' => null, 'review_note' => null]);
            $this->audit->record('payout_account.submitted', $account, null, ['account_last4' => $account->account_last4, 'ifsc' => $account->ifsc], $instructor);
        }
        $account->save();

        return $account;
    }

    public function verify(PayoutAccount $account, User $admin): PayoutAccount
    {
        $this->expectSubmitted($account);
        $account->forceFill(['status' => PayoutAccountStatus::Verified, 'verified_at' => now(), 'verified_by' => $admin->id, 'review_note' => null])->save();
        $this->audit->record('payout_account.verified', $account, null, null, $admin);
        $account->user->notify(new PayoutAccountReviewed($account));

        return $account;
    }

    public function reject(PayoutAccount $account, User $admin, string $note): PayoutAccount
    {
        $this->expectSubmitted($account);
        $account->forceFill(['status' => PayoutAccountStatus::Rejected, 'review_note' => $note])->save();
        $this->audit->record('payout_account.rejected', $account, null, ['note' => $note], $admin);
        $account->user->notify(new PayoutAccountReviewed($account));

        return $account;
    }

    private function expectSubmitted(PayoutAccount $account): void
    {
        if ($account->status !== PayoutAccountStatus::Submitted) {
            throw new DomainException('ALREADY_REVIEWED', 'These details have already been reviewed.', 409);
        }
    }
}
