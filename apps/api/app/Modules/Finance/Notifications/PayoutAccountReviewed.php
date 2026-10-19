<?php

namespace App\Modules\Finance\Notifications;

use App\Modules\Finance\Enums\PayoutAccountStatus;
use App\Modules\Finance\Models\PayoutAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutAccountReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PayoutAccount $account) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if ($this->account->status === PayoutAccountStatus::Verified) {
            return (new MailMessage)
                ->subject('Your payout details are verified')
                ->line("Your bank account ending {$this->account->account_last4} is verified. Earnings will be paid to it each month.")
                ->action('View your revenue', "{$frontend}/instructor/revenue");
        }

        return (new MailMessage)
            ->subject('Please check your payout details')
            ->line('We couldn’t verify the payout details you submitted.')
            ->line('Reason: '.$this->account->review_note)
            ->action('Update payout details', "{$frontend}/instructor/payout-details");
    }
}
