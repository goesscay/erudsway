<?php

namespace App\Modules\Authoring\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authoring\Http\Resources\AdminCourseResource;
use App\Modules\Authoring\Services\CourseReadiness;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Every course on the platform, whatever its state, and the levers only the Erudsway team
 * holds: publishing without a review, archiving, handing a course to another instructor
 * and featuring it. Editing content happens in the normal course builder. Requires the
 * `manage_courses` permission.
 */
class CourseManagementController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Search all courses. Most recently edited first unless another order is asked for. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(CourseStatus::class)],
            'category' => ['sometimes', 'string', 'exists:categories,slug'],
            'featured' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['updated', 'newest', 'enrollments', 'title'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $courses = Course::query()
            ->with(['category.parent', 'instructor', 'priceTier'])
            ->when($validated['q'] ?? null, function (Builder $query, string $q) {
                $term = '%'.mb_strtolower($q).'%';
                $query->where(fn (Builder $w) => $w
                    ->whereRaw('lower(title) like ?', [$term])
                    ->orWhere('slug', 'like', $term)
                    ->orWhereHas('instructor', fn (Builder $u) => $u->whereRaw('lower(name) like ?', [$term])->orWhere('email', 'like', $term)));
            })
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['category'] ?? null, function (Builder $query, string $slug) {
                $category = Category::query()->where('slug', $slug)->with('children')->firstOrFail();
                $query->whereIn('category_id', $category->selfAndChildIds());
            })
            ->when(array_key_exists('featured', $validated), fn (Builder $query) => $request->boolean('featured')
                ? $query->whereNotNull('featured_at')
                : $query->whereNull('featured_at'))
            ->tap(fn (Builder $query) => match ($validated['sort'] ?? 'updated') {
                'newest' => $query->latest('created_at'),
                'enrollments' => $query->orderByDesc('enrollment_count'),
                'title' => $query->orderBy('title'),
                default => $query->latest('updated_at'),
            })
            ->orderBy('id')
            ->paginate(25);

        return AdminCourseResource::collection($courses);
    }

    /**
     * Put a course live without a review — for courses the team built or has checked. The
     * course must still pass the same readiness checklist instructors see.
     */
    public function publish(Request $request, Course $course, CourseReadiness $readiness): AdminCourseResource
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        match ($course->status) {
            CourseStatus::Published => throw new DomainException('ALREADY_PUBLISHED', 'This course is already live.', 422),
            CourseStatus::InReview => throw new DomainException('IN_REVIEW', 'This course is waiting for review. Approve it from Course reviews instead.', 409),
            CourseStatus::Archived => throw new DomainException('ARCHIVED', 'Restore this course before publishing it.', 422),
            default => null,
        };
        $missing = collect($readiness->check($course))->reject(fn (array $item) => $item['ok'])->pluck('label');
        if ($missing->isNotEmpty()) {
            throw new DomainException('COURSE_NOT_READY', 'This course isn’t ready yet: '.$missing->implode('; ').'.', 422);
        }

        $before = $course->status->value;
        $course->forceFill(['status' => CourseStatus::Published, 'published_at' => $course->published_at ?? now()])->save();
        $this->audit->record('course.published_by_staff', $course, ['status' => $before], ['status' => 'published', 'reason' => $data['reason'] ?? null]);

        return $this->row($course);
    }

    /** Retire a course: gone from the catalogue and read-only. Enrolled learners keep access. */
    public function archive(Request $request, Course $course): AdminCourseResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);

        if ($course->status === CourseStatus::Archived) {
            throw new DomainException('ALREADY_ARCHIVED', 'This course is already archived.', 422);
        }
        if ($course->status === CourseStatus::InReview) {
            throw new DomainException('IN_REVIEW', 'Decide the pending review first.', 409);
        }

        $before = $course->status->value;
        $course->forceFill(['status' => CourseStatus::Archived, 'featured_at' => null])->save();
        $this->audit->record('course.archived', $course, ['status' => $before], ['status' => 'archived', 'reason' => $data['reason']]);

        return $this->row($course);
    }

    /** Bring an archived course back for editing: unpublished if it was ever live, else a draft. */
    public function restore(Course $course): AdminCourseResource
    {
        if ($course->status !== CourseStatus::Archived) {
            throw new DomainException('NOT_ARCHIVED', 'Only archived courses can be restored.', 422);
        }

        $status = $course->published_at !== null ? CourseStatus::Unpublished : CourseStatus::Draft;
        $course->forceFill(['status' => $status])->save();
        $this->audit->record('course.restored', $course, ['status' => 'archived'], ['status' => $status->value]);

        return $this->row($course);
    }

    /**
     * Hand the course to another approved instructor. Sales already made stay credited to
     * the previous instructor; new sales, Q&A and announcements go to the new one.
     */
    public function changeInstructor(Request $request, Course $course): AdminCourseResource
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $instructor = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();
        if ($instructor === null || ! $instructor->hasRole(Role::Instructor->value) || $instructor->isSuspended()) {
            throw new DomainException('NOT_AN_INSTRUCTOR', 'No active, approved instructor has that email address.', 422);
        }
        if ($instructor->id === $course->instructor_id) {
            throw new DomainException('SAME_INSTRUCTOR', 'That instructor already owns this course.', 422);
        }

        $previous = $course->instructor;
        $course->forceFill(['instructor_id' => $instructor->id])->save();
        $this->audit->record('course.instructor_changed', $course, ['instructor' => $previous->uuid], ['instructor' => $instructor->uuid]);

        return $this->row($course->unsetRelation('instructor'));
    }

    /** Pin a live course to the top of the homepage and its category pages, or unpin it. */
    public function feature(Request $request, Course $course): AdminCourseResource
    {
        $request->validate(['featured' => ['required', 'boolean']]);
        $featured = $request->boolean('featured');

        if ($featured && $course->status !== CourseStatus::Published) {
            throw new DomainException('NOT_PUBLISHED', 'Only live courses can be featured.', 422);
        }
        if ($featured !== ($course->featured_at !== null)) {
            $course->forceFill(['featured_at' => $featured ? now() : null])->save();
            $this->audit->record($featured ? 'course.featured' : 'course.unfeatured', $course);
        }

        return $this->row($course);
    }

    private function row(Course $course): AdminCourseResource
    {
        return new AdminCourseResource($course->loadMissing(['category.parent', 'instructor', 'priceTier']));
    }
}
