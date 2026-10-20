<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Learning\Services\Entitlements;

/** Whether a course can be bought by this person right now, and if not, why. */
class Purchasability
{
    public function __construct(private readonly Entitlements $entitlements) {}

    /**
     * Null when purchasable; otherwise an error code. A learner who already has the course may
     * still buy a higher edition (an upgrade); one whose access has expired may buy it again.
     */
    public function problem(Course $course, ?User $user, ?CourseEdition $edition = null): ?string
    {
        if ($course->status !== CourseStatus::Published || $course->published_at === null) {
            return 'COURSE_UNAVAILABLE';
        }
        if ($edition !== null && $edition->course_id !== $course->id) {
            return 'COURSE_UNAVAILABLE';
        }
        if ($course->priceTier === null && $edition === null) {
            return 'COURSE_FREE';
        }
        if ($user !== null) {
            if ($course->instructor_id === $user->id) {
                return 'OWN_COURSE';
            }
            $enrollment = $this->entitlements->enrollment($user, $course);
            if ($enrollment !== null) {
                $current = $enrollment->edition;
                $isUpgrade = $edition !== null && $current !== null && $edition->position > $current->position;

                return $isUpgrade ? null : ($current !== null && $edition !== null ? 'EDITION_OWNED' : 'ALREADY_ENROLLED');
            }
        }

        return null;
    }

    public static function message(string $problem): string
    {
        return match ($problem) {
            'COURSE_UNAVAILABLE' => 'This course is no longer available.',
            'COURSE_FREE' => 'This course is free — enroll from the course page.',
            'OWN_COURSE' => 'You can’t buy your own course.',
            'ALREADY_ENROLLED' => 'You already have this course.',
            'EDITION_OWNED' => 'You already have this edition or a higher one.',
            default => 'This course can’t be bought right now.',
        };
    }
}
