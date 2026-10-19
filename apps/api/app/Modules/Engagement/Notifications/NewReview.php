<?php

namespace App\Modules\Engagement\Notifications;

use App\Modules\Engagement\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** To the instructor: a learner rated their course. */
class NewReview extends EngagementNotification
{
    public function __construct(public readonly Review $review) {}

    protected function topic(): string
    {
        return 'instructor_reviews';
    }

    protected function content(): array
    {
        $r = $this->review->loadMissing('course', 'user');

        return [
            'title' => "New {$r->rating}-star review of {$r->course->title}",
            'body' => $r->body ? Str::limit($r->body, 140) : "{$r->user->name} left a rating.",
            'url' => "/instructor/reviews?review={$r->id}",
        ];
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $r = $this->review;
        $mail->line($this->plain($r->user->name).' rated “'.$this->plain($r->course->title).'” '.str_repeat('★', $r->rating).str_repeat('☆', 5 - $r->rating));

        return $r->body ? $mail->line($this->plain(Str::limit($r->body, 600)))->line('You can reply publicly from your Reviews page.') : $mail;
    }
}
