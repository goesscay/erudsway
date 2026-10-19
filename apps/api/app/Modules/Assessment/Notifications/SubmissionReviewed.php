<?php

namespace App\Modules\Assessment\Notifications;

use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Engagement\Notifications\EngagementNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** To the learner: the instructor reviewed their assignment. */
class SubmissionReviewed extends EngagementNotification
{
    public function __construct(public readonly AssignmentSubmission $submission) {}

    protected function topic(): string
    {
        return 'assignment_feedback';
    }

    protected function content(): array
    {
        $lecture = $this->submission->assignment->lecture;

        return [
            'title' => "Feedback on “{$lecture->title}”",
            'body' => "Your instructor reviewed your assignment in {$lecture->course->title}.",
            'url' => "/learn/{$lecture->course->slug}?lecture={$lecture->id}",
        ];
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $s = $this->submission;
        $lecture = $s->assignment->lecture;

        return $mail->greeting('Your assignment has feedback')
            ->line('Your instructor reviewed **'.$this->plain($lecture->title).'** in '.$this->plain($lecture->course->title).':')
            ->line($this->plain((string) $s->feedback));
    }
}
