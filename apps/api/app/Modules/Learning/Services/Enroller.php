<?php

namespace App\Modules\Learning\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Learning\Enums\EnrollmentSource;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Notifications\CourseAccessGranted;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates and ends enrollments. Purchases (Phase 5) will activate enrollments from the
 * payment pipeline; this phase adds free courses and admin grants.
 */
class Enroller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function enrollFree(User $user, Course $course): Enrollment
    {
        if ($course->status !== CourseStatus::Published) {
            throw new DomainException('COURSE_UNAVAILABLE', 'This course isn’t available right now.', 404);
        }
        if (! $course->isFree()) {
            throw new DomainException('COURSE_NOT_FREE', 'This course must be purchased.', 402);
        }
        if ($course->instructor_id === $user->id) {
            throw new DomainException('OWN_COURSE', 'You can’t enroll in your own course.', 422);
        }

        return $this->activate($user, $course, EnrollmentSource::Free);
    }

    public function grant(User $learner, Course $course, User $admin, string $reason): Enrollment
    {
        if (! in_array($course->status, [CourseStatus::Published, CourseStatus::Unpublished], true)) {
            throw new DomainException('COURSE_UNAVAILABLE', 'Only courses that have been published can be granted.', 422);
        }

        $enrollment = $this->activate($learner, $course, EnrollmentSource::AdminGrant, $admin, $reason);
        $this->audit->record('enrollment.granted', $enrollment, null, ['course_id' => $course->id, 'user_id' => $learner->id, 'reason' => $reason], $admin);
        $learner->notify(new CourseAccessGranted($course));

        return $enrollment;
    }

    public function revoke(Enrollment $enrollment, User $admin, string $reason): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $admin, $reason) {
            $locked = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            if (! $locked->isActive()) {
                throw new DomainException('NOT_ACTIVE', 'This enrollment is not active.', 409);
            }

            $locked->forceFill(['status' => EnrollmentStatus::Revoked, 'revoked_at' => now(), 'reason' => $reason])->save();
            Course::query()->whereKey($locked->course_id)->where('enrollment_count', '>', 0)->decrement('enrollment_count');
            $this->audit->record('enrollment.revoked', $locked, ['status' => 'active'], ['status' => 'revoked', 'reason' => $reason], $admin);

            return $locked;
        });
    }

    /**
     * Learners re-enrolling get their existing active enrollment back unchanged. An admin
     * grant can restore revoked access; a learner can't undo a revocation themselves.
     */
    private function activate(User $user, Course $course, EnrollmentSource $source, ?User $grantor = null, ?string $reason = null): Enrollment
    {
        return DB::transaction(function () use ($user, $course, $source, $grantor, $reason) {
            $existing = Enrollment::query()->lockForUpdate()
                ->where('user_id', $user->id)->where('course_id', $course->id)->first();

            if ($existing?->isActive()) {
                if ($grantor !== null) {
                    throw new DomainException('ALREADY_ENROLLED', 'This learner already has access to the course.', 409);
                }

                return $existing;
            }
            if ($existing?->status === EnrollmentStatus::Revoked && $grantor === null) {
                throw new DomainException('ENROLLMENT_REVOKED', 'Your access to this course was removed. Contact support if you think this is a mistake.', 403);
            }

            $enrollment = $existing ?? new Enrollment(['user_id' => $user->id, 'course_id' => $course->id]);
            $enrollment->forceFill([
                'source' => $source,
                'status' => EnrollmentStatus::Active,
                'granted_by' => $grantor?->id,
                'reason' => $reason,
                'granted_at' => now(),
                'revoked_at' => null,
            ])->save();
            Course::query()->whereKey($course->id)->increment('enrollment_count');

            return $enrollment;
        });
    }
}
