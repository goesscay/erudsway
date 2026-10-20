<?php

namespace App\Modules\Learning\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Learning\Enums\EnrollmentSource;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Events\EditionGranted;
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

    /**
     * Access bought through an order item. A purchase restores revoked or expired access; for
     * a course with editions it sets (or upgrades to) the edition bought.
     */
    public function enrollPurchase(User $user, Course $course, int $orderItemId, ?CourseEdition $edition = null): Enrollment
    {
        $enrollment = $this->activate($user, $course, EnrollmentSource::Purchase, orderItemId: $orderItemId);

        return $edition ? $this->applyEdition($enrollment, $edition) : $enrollment;
    }

    /**
     * Give an enrollment an edition — a purchase, an upgrade or a renewal. The access period
     * runs from now (never shortening what's left); an edition without one has no end.
     */
    public function applyEdition(Enrollment $enrollment, CourseEdition $edition): Enrollment
    {
        $previous = $enrollment->edition;
        $ends = $edition->access_days === null ? null : now()->addDays($edition->access_days);
        if ($ends !== null && $enrollment->expires_at !== null && $enrollment->expires_at->isAfter($ends) && $previous !== null) {
            $ends = $enrollment->expires_at;
        }
        $enrollment->forceFill(['edition_id' => $edition->id, 'expires_at' => $ends])->save();
        $enrollment->setRelation('edition', $edition);
        EditionGranted::dispatch($enrollment, $previous);

        return $enrollment;
    }

    /**
     * Undo a refunded purchase. Refunding an upgrade puts the learner back on the edition they
     * upgraded from; refunding the course itself ends access.
     */
    public function markRefunded(User $user, Course $course, ?CourseEdition $revertTo = null): void
    {
        if ($revertTo !== null) {
            Enrollment::query()->where('user_id', $user->id)->where('course_id', $course->id)->where('status', EnrollmentStatus::Active->value)
                ->update(['edition_id' => $revertTo->id]);

            return;
        }

        DB::transaction(function () use ($user, $course) {
            $enrollment = Enrollment::query()->lockForUpdate()
                ->where('user_id', $user->id)->where('course_id', $course->id)->first();
            if ($enrollment?->isActive()) {
                $enrollment->forceFill(['status' => EnrollmentStatus::Refunded, 'revoked_at' => now()])->save();
                Course::query()->whereKey($course->id)->where('enrollment_count', '>', 0)->decrement('enrollment_count');
            }
        });
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
     * grant or a purchase can restore revoked access; a free re-enroll can't.
     */
    private function activate(User $user, Course $course, EnrollmentSource $source, ?User $grantor = null, ?string $reason = null, ?int $orderItemId = null): Enrollment
    {
        return DB::transaction(function () use ($user, $course, $source, $grantor, $reason, $orderItemId) {
            $existing = Enrollment::query()->lockForUpdate()
                ->where('user_id', $user->id)->where('course_id', $course->id)->first();

            if ($existing?->isActive()) {
                if ($grantor !== null) {
                    throw new DomainException('ALREADY_ENROLLED', 'This learner already has access to the course.', 409);
                }

                return $existing;
            }
            if ($existing?->status === EnrollmentStatus::Revoked && $grantor === null && $orderItemId === null) {
                throw new DomainException('ENROLLMENT_REVOKED', 'Your access to this course was removed. Contact support if you think this is a mistake.', 403);
            }

            $enrollment = $existing ?? new Enrollment(['user_id' => $user->id, 'course_id' => $course->id]);
            $enrollment->forceFill([
                'source' => $source,
                'status' => EnrollmentStatus::Active,
                'granted_by' => $grantor?->id,
                'reason' => $reason,
                'order_item_id' => $orderItemId,
                'granted_at' => now(),
                'revoked_at' => null,
            ])->save();
            Course::query()->whereKey($course->id)->increment('enrollment_count');

            return $enrollment;
        });
    }
}
