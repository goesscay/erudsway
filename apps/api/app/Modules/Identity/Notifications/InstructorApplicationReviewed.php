<?php

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\Enums\ApplicationStatus;
use App\Modules\Identity\Models\InstructorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstructorApplicationReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly InstructorApplication $application) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if ($this->application->status === ApplicationStatus::Approved) {
            return (new MailMessage)
                ->subject('You are now an Erudsway instructor')
                ->greeting('Welcome aboard!')
                ->line('Your application to teach on Erudsway has been approved.')
                ->lineIf((bool) $this->application->review_notes, (string) $this->application->review_notes)
                ->action('Create your first course', "{$frontend}/instructor")
                ->line('Every course is reviewed by our team before it is published.');
        }

        return (new MailMessage)
            ->subject('About your Erudsway instructor application')
            ->line('Thank you for applying to teach on Erudsway. We are not able to approve your application right now.')
            ->line('Reviewer notes: '.$this->application->review_notes)
            ->line('You are welcome to apply again once you have addressed the points above.')
            ->action('View your application', "{$frontend}/teach");
    }
}
