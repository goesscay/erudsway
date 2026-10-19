<?php

namespace App\Modules\Finance\Notifications;

use App\Modules\Finance\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payout $payout) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payout;
        $rupees = fn (int $paise) => '₹'.number_format($paise / 100, 2);
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject('Your Erudsway payout has been sent')
            ->line("We’ve transferred {$rupees($p->net)} to your account ending {$p->bank_snapshot['account_last4']}.")
            ->line("Earnings {$rupees($p->gross)} · TDS under section {$p->tds_section}: {$rupees($p->tds)} · Bank reference (UTR): {$p->reference}")
            ->action('View your revenue', "{$frontend}/instructor/revenue")
            ->line('TDS is reported against your PAN; it appears in your Form 26AS after the quarterly return is filed.');
    }
}
