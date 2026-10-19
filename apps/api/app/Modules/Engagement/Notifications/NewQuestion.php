<?php

namespace App\Modules\Engagement\Notifications;

use App\Modules\Engagement\Models\Question;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** To the instructor: a learner asked something in their course. */
class NewQuestion extends EngagementNotification
{
    public function __construct(public readonly Question $question) {}

    protected function topic(): string
    {
        return 'instructor_questions';
    }

    protected function content(): array
    {
        $q = $this->question->loadMissing('course', 'user');

        return [
            'title' => "New question in {$q->course->title}",
            'body' => "{$q->user->name}: {$q->title}",
            'url' => "/instructor/qa?question={$q->id}",
        ];
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $q = $this->question;

        return $mail->line($this->plain($q->user->name).' asked a question in “'.$this->plain($q->course->title).'”:')
            ->line('**'.$this->plain($q->title).'**')
            ->line($this->plain(Str::limit($q->body, 400)));
    }
}
