<?php

namespace App\Modules\Learning\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Models\Enrollment;

/**
 * The single answer to "may this person see this?". Every playback, key, content and
 * download request goes through here, so revoking an enrollment takes effect at once.
 */
class Entitlements
{
    public const ROLE_LEARNER = 'learner';

    public const ROLE_INSTRUCTOR = 'instructor';

    public const ROLE_REVIEWER = 'reviewer';

    /** How the user relates to the course, or null if they have no access to its content. */
    public function role(?User $user, Course $course): ?string
    {
        if ($user === null || $user->isSuspended()) {
            return null;
        }
        if ($course->instructor_id === $user->id) {
            return self::ROLE_INSTRUCTOR;
        }
        if ($this->enrollment($user, $course) !== null) {
            return self::ROLE_LEARNER;
        }
        if ($user->can(Permission::ModerateCourses->value)) {
            return self::ROLE_REVIEWER;
        }

        return null;
    }

    public function canAccessCourse(?User $user, Course $course): bool
    {
        return $this->role($user, $course) !== null;
    }

    /** Free-preview lectures of a live course are open to everyone, signed in or not. */
    public function canView(?User $user, Lecture $lecture): bool
    {
        if ($lecture->is_free_preview && $lecture->course->status === CourseStatus::Published) {
            return true;
        }

        return $this->canAccessCourse($user, $lecture->course);
    }

    public function enrollment(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()->active()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
    }
}
