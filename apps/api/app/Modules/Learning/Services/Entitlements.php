<?php

namespace App\Modules\Learning\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Media\Models\LectureResource;
use App\Support\Http\DomainException;

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

    /**
     * Free-preview lectures of a live course are open to everyone, signed in or not. Other
     * lectures need access to the course, and — for learners — an edition that includes them.
     */
    public function canView(?User $user, Lecture $lecture): bool
    {
        if ($lecture->is_free_preview && $lecture->course->status === CourseStatus::Published) {
            return true;
        }

        return $this->canAccessCourse($user, $lecture->course) && $this->lockedBehind($user, $lecture) === null;
    }

    /**
     * The edition a learner needs to open this lecture, when theirs doesn't include it.
     * Instructors and reviewers see everything; free previews are never locked.
     */
    public function lockedBehind(?User $user, Lecture $lecture): ?CourseEdition
    {
        return $this->lock($user, $lecture->course, $lecture->is_free_preview ? null : $lecture->minEdition);
    }

    /** Same for a downloadable file, which can be held back further than its lecture. */
    public function resourceLockedBehind(?User $user, LectureResource $resource): ?CourseEdition
    {
        return $this->lockedBehind($user, $resource->lecture) ?? $this->lock($user, $resource->lecture->course, $resource->minEdition);
    }

    /** The error for content held back to a higher edition (the player offers the upgrade). */
    public static function lockedError(CourseEdition $needed): DomainException
    {
        return new DomainException('EDITION_LOCKED', "This is part of the {$needed->name} edition. Upgrade to open it.", 403);
    }

    private function lock(?User $user, Course $course, ?CourseEdition $minimum): ?CourseEdition
    {
        if ($minimum === null || $user === null || $this->role($user, $course) !== self::ROLE_LEARNER) {
            return null;
        }
        $edition = $this->enrollment($user, $course)?->edition;

        // No edition on the enrollment means the whole course (grants, single-price courses).
        return $edition === null || $edition->covers($minimum) ? null : $minimum;
    }

    public function enrollment(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()->active()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();
    }
}
