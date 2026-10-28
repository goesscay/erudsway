<?php

namespace App\Modules\Support\Notifications;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A new ticket, or a learner's reply, sent to the support inbox. Answer it from the admin console. */
class TicketAwaitsSupport extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketMessage $message,
        public readonly bool $isNew,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->ticket->loadMissing('user:id,name,email', 'course:id,title');
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $mail = (new MailMessage)
            ->subject(($this->isNew ? 'New ticket' : 'Learner replied')." {$t->number}: ".mb_substr($t->subject, 0, 80))
            ->greeting($this->isNew ? 'A learner raised a support ticket' : 'A learner replied to their ticket')
            ->line('**From:** '.Markdown::escape($t->user->name).' ('.Markdown::escape($t->user->email).')')
            ->line('**Topic:** '.$t->category->label().($t->course ? ' · '.Markdown::escape($t->course->title) : ''))
            ->line('**Subject:** '.Markdown::escape($t->subject));
        foreach (preg_split('/\R{2,}/', trim($this->message->body)) ?: [] as $paragraph) {
            $mail->line(Markdown::escape($paragraph));
        }

        return $mail
            ->line('Please answer from the admin console so the learner can follow the conversation.')
            ->action('Open the ticket', "{$frontend}/admin/support/{$t->uuid}");
    }
}
