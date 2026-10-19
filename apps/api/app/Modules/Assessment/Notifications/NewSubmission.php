<?php

namespace App\Modules\Assessment\Notifications;

use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Engagement\Notifications\EngagementNotification;
use Illuminate\Notifications\Messages\MailMessage;

/** To the instructor: a learner handed in an assignment. */
class NewSubmission extends EngagementNotification
{
    public function __construct(public readonly AssignmentSubmission $submission) {}

    protected function topic(): string
    {
        return 'instructor_submissions';
    }

    protected function content(): array
    {
        $s = $this->submission;
        $lecture = $s->assignment->lecture;

        return [
            'title' => "New assignment submission in {$lecture->course->title}",
            'body' => "{$s->user->name} handed in “{$lecture->title}”.",
            'url' => "/instructor/assignments?submission={$s->id}",
        ];
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $s = $this->submission;
        $lecture = $s->assignment->lecture;

        return $mail->greeting('A learner handed in an assignment')
            ->line($this->plain($s->user->name).' submitted **'.$this->plain($lecture->title).'** in '.$this->plain($lecture->course->title).'.')
            ->line('Leave feedback so they know how they did.');
    }
}
