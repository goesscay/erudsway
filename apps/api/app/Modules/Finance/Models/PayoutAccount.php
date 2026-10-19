<?php

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Finance\Enums\PayoutAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An instructor's KYC and bank details for payouts. PAN and account number are encrypted
 * at rest and only ever shown masked. Changing them sends the account back for verification.
 *
 * @property int $user_id
 * @property string $legal_name
 * @property string $pan
 * @property string $pan_last4
 * @property string|null $gstin
 * @property string $account_holder
 * @property string $account_number
 * @property string $account_last4
 * @property string $ifsc
 * @property PayoutAccountStatus $status
 * @property string|null $review_note
 * @property Carbon $submitted_at
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property-read User $user
 */
class PayoutAccount extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['pan', 'account_number'];

    protected function casts(): array
    {
        return [
            'pan' => 'encrypted',
            'account_number' => 'encrypted',
            'status' => PayoutAccountStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->status === PayoutAccountStatus::Verified;
    }
}
