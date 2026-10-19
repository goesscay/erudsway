<?php

namespace App\Modules\Engagement\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Engagement\Jobs\SendAnnouncement;
use App\Modules\Engagement\Models\Announcement;
use App\Support\Html\HtmlSanitizer;
use App\Support\Http\DomainException;

/** Instructor updates to everyone enrolled in a course. */
class Announcements
{
    /** Keeps announcements useful rather than promotional (Udemy allows a similar handful a month). */
    public const PER_30_DAYS = 4;

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function post(User $instructor, Course $course, string $title, string $bodyHtml): Announcement
    {
        if ($course->instructor_id !== $instructor->id) {
            throw new DomainException('NOT_FOUND', 'Course not found.', 404);
        }
        if ($course->status !== CourseStatus::Published) {
            throw new DomainException('COURSE_NOT_LIVE', 'Announcements can be sent once the course is published.', 422);
        }
        $recent = Announcement::query()->where('course_id', $course->id)->where('created_at', '>=', now()->subDays(30))->count();
        if ($recent >= self::PER_30_DAYS) {
            throw new DomainException('ANNOUNCEMENT_LIMIT', 'You can send up to '.self::PER_30_DAYS.' announcements per course every 30 days.', 429);
        }

        $body = (string) $this->sanitizer->clean($bodyHtml);
        if (HtmlSanitizer::textLength($body) < 20) {
            throw new DomainException('VALIDATION_FAILED', 'Write at least a couple of sentences.', 422);
        }

        $announcement = Announcement::query()->create([
            'course_id' => $course->id,
            'user_id' => $instructor->id,
            'title' => trim($title),
            'body_html' => $body,
        ]);
        SendAnnouncement::dispatch($announcement->id);

        return $announcement;
    }
}
