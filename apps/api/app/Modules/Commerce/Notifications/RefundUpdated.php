<?php

namespace App\Modules\Commerce\Notifications;

use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Models\Refund;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Refund $refund) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $refund = $this->refund->loadMissing('orderItem');
        $course = $refund->orderItem->course_title;
        $title = Markdown::escape($course); // for the Markdown body; subjects are plain text
        $amount = '₹'.number_format($refund->amount / 100, 2);
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $mail = match ($refund->status) {
            RefundStatus::Requested => (new MailMessage)->subject("Refund request received: {$course}")
                ->line("We’ve received your refund request for “{$title}”. Our team will review it within two working days."),
            RefundStatus::Rejected => (new MailMessage)->subject("Refund request declined: {$course}")
                ->line("We couldn’t approve your refund request for “{$title}”.")
                ->lineIf((bool) $refund->decision_note, 'Reason: '.$refund->decision_note),
            RefundStatus::Processing => (new MailMessage)->subject("Refund on its way: {$course}")
                ->line("Your refund of {$amount} for “{$title}” has been issued. Banks usually take 5–7 working days to show it."),
            RefundStatus::Processed => (new MailMessage)->subject("Refund completed: {$course}")
                ->line("Your refund of {$amount} for “{$title}” is complete."),
            RefundStatus::Failed => (new MailMessage)->subject("Refund delayed: {$course}")
                ->line("There was a problem issuing your refund for “{$title}”. Our team has been alerted and will complete it."),
        };

        return $mail->action('Purchase history', "{$frontend}/account/purchases");
    }
}
