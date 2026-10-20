<?php

namespace App\Modules\Fulfilment\Notifications;

use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Support\Mail\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SimulatorAccessReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly FulfilmentTask $task) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $course = $this->task->course;

        // The password isn't emailed: it's shown on the course page, behind the learner's login.
        return (new MailMessage)
            ->subject('Your GST simulator access is ready')
            ->line('Your practice login for the GST simulator, part of “'.Markdown::escape($course->title).'”, is ready.')
            ->line('Open it from your course page — one click signs you in.')
            ->action('Open my course', "{$frontend}/learn/{$course->slug}?extras=1");
    }
}
