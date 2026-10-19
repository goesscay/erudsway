<?php

namespace App\Modules\Learning\Notifications;

use App\Modules\Catalog\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseAccessGranted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Course $course) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject("You now have access to {$this->course->title}")
            ->line("The Erudsway team has added “{$this->course->title}” to your learning.")
            ->action('Start learning', "{$frontend}/learn/{$this->course->slug}");
    }
}
