<?php

namespace App\Modules\Support\Notifications;

use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the learner the support team has answered their ticket. */
class SupportTicketUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketMessage $message,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->ticket;
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $mail = (new MailMessage)
            ->subject("Re: {$t->number} — ".mb_substr($t->subject, 0, 80))
            ->greeting('Our support team has replied')
            ->line('About your ticket **'.Markdown::escape($t->subject).'**:');
        foreach (preg_split('/\R{2,}/', trim($this->message->body)) ?: [] as $paragraph) {
            $mail->line(Markdown::escape($paragraph));
        }
        if ($t->status === TicketStatus::Resolved) {
            $mail->line('We have marked this ticket resolved. If something is still not right, just reply on the ticket and it will reopen.');
        }

        return $mail->action('View the ticket', "{$frontend}/help/tickets/{$t->uuid}");
    }
}
