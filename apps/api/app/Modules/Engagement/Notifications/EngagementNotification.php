<?php

namespace App\Modules\Engagement\Notifications;

use App\Models\User;
use App\Modules\Engagement\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An optional notification: shown in the in-app bell and emailed, unless the user has
 * switched its topic off in their preferences.
 */
abstract class EngagementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** One of NotificationPreference::TOPICS. */
    abstract protected function topic(): string;

    /** @return array{title: string, body: string, url: string} */
    abstract protected function content(): array;

    abstract protected function mail(MailMessage $mail): MailMessage;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! NotificationPreference::for($notifiable->id)->getAttribute($this->topic())) {
            return [];
        }

        return ['database', 'mail'];
    }

    /** @return array{kind: string, title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->topic(), ...$this->content()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->content();

        return $this->mail((new MailMessage)->subject($content['title']))
            ->action('Open on Erudsway', $this->link($content['url']))
            ->line('You can choose which emails you get in your account settings.');
    }

    /** Escape user-written text for the Markdown mail template, so it can't add links or formatting. */
    protected function plain(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#+\-.!|>~<])/', '\\\\$1', $text);
    }

    protected function link(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
