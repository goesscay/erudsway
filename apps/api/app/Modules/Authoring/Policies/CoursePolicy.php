<?php

namespace App\Modules\Authoring\Policies;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Auth\Access\Response;

/**
 * Instructors manage only their own courses. Courses under review are locked; archived
 * courses are read-only. Moderators can view any course.
 */
class CoursePolicy
{
    /** Strangers get a 404, so course ids can't be probed. */
    public function view(User $user, Course $course): Response
    {
        return $this->owns($user, $course) || $user->can(Permission::ModerateCourses->value)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Course $course): Response
    {
        if (! $this->owns($user, $course)) {
            return Response::denyAsNotFound();
        }

        return match ($course->status) {
            CourseStatus::InReview => Response::deny('This course is being reviewed. You can edit it again once the review is complete.'),
            CourseStatus::Archived => Response::deny('Archived courses cannot be edited.'),
            default => Response::allow(),
        };
    }

    public function delete(User $user, Course $course): Response
    {
        if (! $this->owns($user, $course)) {
            return Response::denyAsNotFound();
        }

        return $course->status === CourseStatus::Draft && $course->published_at === null
            ? Response::allow()
            : Response::deny('Only unpublished drafts can be deleted.');
    }

    public function submit(User $user, Course $course): Response
    {
        if (! $this->owns($user, $course)) {
            return Response::denyAsNotFound();
        }

        return in_array($course->status, [CourseStatus::Draft, CourseStatus::ChangesRequested, CourseStatus::Unpublished], true)
            ? Response::allow()
            : Response::deny('This course cannot be submitted in its current state.');
    }

    private function owns(User $user, Course $course): bool
    {
        return $course->instructor_id === $user->id && $user->isInstructor();
    }
}
