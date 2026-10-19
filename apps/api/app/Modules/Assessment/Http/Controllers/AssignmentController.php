<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Http\Resources\SubmissionResource;
use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Assessment\Services\Assignments;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Services\Entitlements;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function __construct(private readonly Assignments $assignments) {}

    /** Instructor: the brief, for the course builder. */
    public function edit(Lecture $lecture): JsonResponse
    {
        Gate::authorize('view', $lecture->course);
        $a = $this->assignments->for($lecture);

        return ApiResponse::data(['lecture_id' => $lecture->id, 'instructions_html' => $a->instructions_html, 'allow_file' => $a->allow_file]);
    }

    public function update(Request $request, Lecture $lecture): JsonResponse
    {
        Gate::authorize('update', $lecture->course);
        $data = $request->validate([
            'instructions_html' => ['nullable', 'string', 'max:20000'],
            'allow_file' => ['required', 'boolean'],
        ]);
        $a = $this->assignments->save($lecture, $data['instructions_html'] ?? null, $data['allow_file']);

        return ApiResponse::data(['lecture_id' => $lecture->id, 'instructions_html' => $a->instructions_html, 'allow_file' => $a->allow_file]);
    }

    /** Learner: the brief and their own submission, if any. */
    public function show(Request $request, Lecture $lecture, Entitlements $entitlements): JsonResponse
    {
        $user = $this->user($request);
        if ($entitlements->role($user, $lecture->course) === null) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to see its assignments.', 403);
        }
        $a = $this->assignments->for($lecture);
        $submission = $this->assignments->submission($user, $a);

        return ApiResponse::data([
            'lecture_id' => $lecture->id,
            // Sanitised with HTMLPurifier when the instructor saved it.
            'instructions_html' => $a->instructions_html,
            'allow_file' => $a->allow_file,
            'file_types' => Assignments::FILE_TYPES,
            'max_file_mb' => intdiv(Assignments::MAX_FILE_KB, 1024),
            'submission' => $submission ? new SubmissionResource($submission) : null,
        ]);
    }

    /** Learner: hand in (or replace) an answer. Multipart: `body` and/or `file`. */
    public function submit(Request $request, Lecture $lecture): JsonResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'file' => ['nullable', 'file', 'max:'.Assignments::MAX_FILE_KB, 'extensions:'.implode(',', Assignments::FILE_TYPES)],
        ]);

        $submission = $this->assignments->submit($this->user($request), $lecture, $data['body'] ?? null, $request->file('file'));

        // 200 whether it's a first hand-in or a replacement.
        return (new SubmissionResource($submission))->response()->setStatusCode(200);
    }

    public function file(Request $request, AssignmentSubmission $submission): StreamedResponse
    {
        $submission->load('assignment.lecture.course');
        if ($submission->file_path === null || ! $this->assignments->canViewFile($this->user($request), $submission)) {
            abort(404);
        }

        return Storage::disk('local')->download($submission->file_path, $submission->file_name);
    }

    /** Instructor: submissions across my courses; waiting for review by default. */
    public function inbox(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['nullable', 'in:submitted,reviewed,all'], 'page' => ['nullable', 'integer', 'min:1']]);
        $status = $data['status'] ?? AssignmentSubmission::SUBMITTED;

        $query = AssignmentSubmission::query()
            ->whereHas('assignment.lecture.course', fn ($q) => $q->where('instructor_id', $this->user($request)->id))
            ->with(['user:id,name', 'assignment.lecture.course:id,slug,title'])
            ->latest('submitted_at');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return SubmissionResource::collection($query->paginate(20));
    }

    public function review(Request $request, AssignmentSubmission $submission): SubmissionResource
    {
        $data = $request->validate(['feedback' => ['required', 'string', 'min:2', 'max:5000']]);
        $submission->load(['assignment.lecture.course', 'user']);

        return new SubmissionResource($this->assignments->review($this->user($request), $submission, $data['feedback']));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
