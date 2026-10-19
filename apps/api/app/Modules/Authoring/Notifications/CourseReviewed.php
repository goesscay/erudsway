<?php

namespace App\Modules\Authoring\Notifications;

use App\Modules\Authoring\Models\CourseSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CourseSubmission $submission) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $course = $this->submission->course;

        if ($this->submission->status === 'approved') {
            return (new MailMessage)
                ->subject("Published: {$course->title}")
                ->greeting('Your course is live')
                ->line("“{$course->title}” has been approved and is now visible to learners.")
                ->lineIf((bool) $this->submission->feedback, (string) $this->submission->feedback)
                ->action('View your course', "{$frontend}/course/{$course->slug}");
        }

        return (new MailMessage)
            ->subject("Changes requested: {$course->title}")
            ->line("Our review team has asked for a few changes to “{$course->title}” before it can be published.")
            ->line('Reviewer feedback: '.$this->submission->feedback)
            ->action('Open the course builder', "{$frontend}/instructor/courses/{$course->uuid}")
            ->line('Once you have made the changes, submit the course again.');
    }
}
