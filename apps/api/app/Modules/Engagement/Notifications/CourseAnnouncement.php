<?php

namespace App\Modules\Engagement\Notifications;

use App\Modules\Engagement\Models\Announcement;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** To enrolled learners: their instructor posted an update. */
class CourseAnnouncement extends EngagementNotification
{
    public function __construct(public readonly Announcement $announcement) {}

    protected function topic(): string
    {
        return 'course_announcements';
    }

    protected function content(): array
    {
        $a = $this->announcement->loadMissing('course');

        return [
            'title' => $a->title,
            'body' => "Announcement in {$a->course->title}",
            'url' => "/learn/{$a->course->slug}?tab=announcements",
        ];
    }

    protected function mail(MailMessage $mail): MailMessage
    {
        $a = $this->announcement;
        $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '</li>'], "\n", $a->body_html)), ENT_QUOTES));

        return $mail->greeting($this->plain($a->title))
            ->line('From your instructor in “'.$this->plain($a->course->title).'”:')
            ->line($this->plain(Str::limit($text, 1500)));
    }
}
