<?php

namespace App\Modules\Engagement\Notifications;

use App\Modules\Engagement\Models\Answer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** To the person who asked: someone answered their question. */
class NewAnswer extends EngagementNotification
{
    public function __construct(public readonly Answer $answer) {}

    protected function topic(): string
    {
        return 'qa_replies';
    }

    protected function content(): array
    {
        $a = $this->answer->loadMissing('question.course', 'user');
        $who = $a->is_instructor ? 'The instructor' : $a->user->name;

        return [
            'title' => "{$who} answered your question",
            'body' => $a->question->title,
            'url' => "/learn/{$a->question->course->slug}?tab=qa&question={$a->question_id}",
        ];
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $a = $this->answer;

        return $mail->line('Your question in “'.$this->plain($a->question->course->title).'”: **'.$this->plain($a->question->title).'**')
            ->line(($a->is_instructor ? 'The instructor' : $this->plain($a->user->name)).' replied:')
            ->line($this->plain(Str::limit($a->body, 600)));
    }
}
