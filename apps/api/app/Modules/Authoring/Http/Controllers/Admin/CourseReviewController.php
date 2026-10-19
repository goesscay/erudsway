<?php

namespace App\Modules\Authoring\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authoring\Http\Resources\CourseEditorResource;
use App\Modules\Authoring\Http\Resources\CourseSubmissionResource;
use App\Modules\Authoring\Models\CourseSubmission;
use App\Modules\Authoring\Notifications\CourseReviewed;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Course moderation. Requires the `moderate_courses` permission.
 */
class CourseReviewController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Submissions, oldest pending first by default. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $status = $validated['status'] ?? 'pending';

        $submissions = CourseSubmission::query()
            ->with(['course.category', 'submitter', 'reviewer'])
            ->where('status', $status)
            ->orderBy($status === 'pending' ? 'created_at' : 'decided_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(20);

        return CourseSubmissionResource::collection($submissions);
    }

    /** Full course content for review, including drafts. */
    public function show(Course $course): CourseEditorResource
    {
        return CourseEditorResource::load($course);
    }

    public function approve(Request $request, CourseSubmission $submission): CourseSubmissionResource
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $submission = DB::transaction(function () use ($request, $submission, $data) {
            $locked = $this->lockPending($submission, $request);
            $course = $locked->course;

            $locked->forceFill([
                'status' => 'approved',
                'reviewer_id' => $this->user($request)->id,
                'feedback' => $data['notes'] ?? null,
                'decided_at' => now(),
            ])->save();
            $course->forceFill([
                'status' => CourseStatus::Published,
                'published_at' => $course->published_at ?? now(),
            ])->save();

            $this->audit->record('course.approved', $course, ['status' => 'in_review'], ['status' => 'published']);

            return $locked;
        });

        $submission->submitter->notify(new CourseReviewed($submission));

        return new CourseSubmissionResource($submission->load(['course.category', 'submitter', 'reviewer']));
    }

    public function reject(Request $request, CourseSubmission $submission): CourseSubmissionResource
    {
        $data = $request->validate(['feedback' => ['required', 'string', 'min:20', 'max:5000']], [
            'feedback.required' => 'Explain what needs to change so the instructor can fix it.',
            'feedback.min' => 'Give the instructor enough detail to act on (at least 20 characters).',
        ]);

        $submission = DB::transaction(function () use ($request, $submission, $data) {
            $locked = $this->lockPending($submission, $request);
            $course = $locked->course;

            $locked->forceFill([
                'status' => 'rejected',
                'reviewer_id' => $this->user($request)->id,
                'feedback' => $data['feedback'],
                'decided_at' => now(),
            ])->save();
            // A previously live course stays unpublished until it passes review again.
            $course->forceFill(['status' => CourseStatus::ChangesRequested])->save();

            $this->audit->record('course.changes_requested', $course, ['status' => 'in_review'], ['status' => 'changes_requested', 'feedback' => $data['feedback']]);

            return $locked;
        });

        $submission->submitter->notify(new CourseReviewed($submission));

        return new CourseSubmissionResource($submission->load(['course.category', 'submitter', 'reviewer']));
    }

    /** Take a live course off the catalogue (e.g. a complaint or a legal issue). */
    public function unpublish(Request $request, Course $course): CourseEditorResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        if ($course->status !== CourseStatus::Published) {
            throw new DomainException('NOT_PUBLISHED', 'Only published courses can be unpublished.', 422);
        }

        $course->forceFill(['status' => CourseStatus::Unpublished])->save();
        $this->audit->record('course.unpublished', $course, ['status' => 'published'], ['status' => 'unpublished', 'reason' => $data['reason']]);

        return CourseEditorResource::load($course);
    }

    private function lockPending(CourseSubmission $submission, Request $request): CourseSubmission
    {
        /** @var CourseSubmission $locked */
        $locked = CourseSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== 'pending') {
            throw new DomainException('SUBMISSION_ALREADY_REVIEWED', 'This submission has already been reviewed.', 409);
        }
        if ($locked->submitted_by === $this->user($request)->id) {
            throw new DomainException('SELF_REVIEW', 'Another moderator must review your own course.', 403);
        }

        return $locked;
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
