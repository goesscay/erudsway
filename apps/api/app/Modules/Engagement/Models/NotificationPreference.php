<?php

namespace App\Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Which optional emails and alerts a user gets. Receipts, refunds, security and account
 * emails are always sent and aren't listed here.
 *
 * @property int $user_id
 * @property bool $course_announcements
 * @property bool $qa_replies
 * @property bool $instructor_questions
 * @property bool $instructor_reviews
 * @property bool $assignment_feedback
 * @property bool $instructor_submissions
 * @property bool $promotions
 */
class NotificationPreference extends Model
{
    public const TOPICS = ['course_announcements', 'qa_replies', 'assignment_feedback', 'instructor_questions', 'instructor_reviews', 'instructor_submissions', 'promotions'];

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    /** @var array<string, bool> */
    protected $attributes = [
        'course_announcements' => true,
        'qa_replies' => true,
        'instructor_questions' => true,
        'instructor_reviews' => true,
        'assignment_feedback' => true,
        'instructor_submissions' => true,
        'promotions' => false,
    ];

    protected function casts(): array
    {
        return array_fill_keys(self::TOPICS, 'boolean');
    }

    public static function for(int $userId): self
    {
        return self::query()->find($userId) ?? new self(['user_id' => $userId]);
    }
}
