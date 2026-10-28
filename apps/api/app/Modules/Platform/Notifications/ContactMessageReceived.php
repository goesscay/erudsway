<?php

namespace App\Modules\Platform\Notifications;

use App\Modules\Platform\Models\ContactMessage;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A Contact page message, forwarded to the support inbox. Replying answers the sender. */
class ContactMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ContactMessage $contact) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contact;
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $mail = (new MailMessage)
            ->subject("Website enquiry: {$c->topic->label()} — ".mb_substr($c->name, 0, 60))
            ->replyTo($c->email, $c->name)
            ->greeting('New message from the Contact page')
            ->line('**From:** '.Markdown::escape($c->name).' ('.Markdown::escape($c->email).')'.($c->phone ? ' · '.Markdown::escape($c->phone) : ''))
            ->line('**Topic:** '.$c->topic->label());
        foreach (preg_split('/\R{2,}/', trim($c->message)) ?: [] as $paragraph) {
            $mail->line(Markdown::escape($paragraph));
        }

        return $mail
            ->line('Reply to this email to answer them directly.')
            ->action('Open in the admin console', "{$frontend}/admin/messages");
    }
}
