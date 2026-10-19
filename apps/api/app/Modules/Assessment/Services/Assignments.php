<?php

namespace App\Modules\Assessment\Services;

use App\Models\User;
use App\Modules\Assessment\Models\Assignment;
use App\Modules\Assessment\Models\AssignmentSubmission;
use App\Modules\Assessment\Notifications\NewSubmission;
use App\Modules\Assessment\Notifications\SubmissionReviewed;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Learning\Services\ProgressTracker;
use App\Support\Html\HtmlSanitizer;
use App\Support\Http\DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Assignments: the instructor writes a brief; each learner hands in one answer (text, a
 * file, or both) which they can replace until it's reviewed. Handing in completes the
 * lecture — feedback is for learning, not a gate on the certificate.
 */
class Assignments
{
    /** Listed length of an assignment lecture. */
    public const DURATION_SECONDS = 600;

    public const FILE_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'zip'];

    public const MAX_FILE_KB = 20 * 1024;

    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly ProgressTracker $progress,
        private readonly HtmlSanitizer $sanitizer,
        private readonly CourseStats $stats,
    ) {}

    public function for(Lecture $lecture): Assignment
    {
        if ($lecture->kind !== LectureKind::Assignment) {
            throw new DomainException('NOT_AN_ASSIGNMENT', 'This lecture isn’t an assignment.', 422);
        }

        return Assignment::query()->firstOrCreate(['lecture_id' => $lecture->id]);
    }

    public function save(Lecture $lecture, ?string $instructions, bool $allowFile): Assignment
    {
        $assignment = $this->for($lecture);
        $assignment->forceFill(['instructions_html' => $this->sanitizer->clean($instructions), 'allow_file' => $allowFile])->save();
        if ($lecture->duration_seconds !== self::DURATION_SECONDS) {
            $lecture->forceFill(['duration_seconds' => self::DURATION_SECONDS])->save();
            $this->stats->refresh($lecture->course_id);
        }

        return $assignment;
    }

    public function submission(User $user, Assignment $assignment): ?AssignmentSubmission
    {
        return AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('user_id', $user->id)->first();
    }

    public function submit(User $user, Lecture $lecture, ?string $body, ?UploadedFile $file): AssignmentSubmission
    {
        if ($this->entitlements->role($user, $lecture->course) !== Entitlements::ROLE_LEARNER) {
            throw new DomainException('NOT_ENROLLED', 'Only learners enrolled in this course can hand in assignments.', 403);
        }
        $assignment = $this->for($lecture);
        $body = $body !== null && trim($body) !== '' ? trim($body) : null;
        if ($file && ! $assignment->allow_file) {
            throw new DomainException('FILE_NOT_ALLOWED', 'This assignment takes a written answer only.', 422);
        }

        $submission = $this->submission($user, $assignment) ?? new AssignmentSubmission(['assignment_id' => $assignment->id, 'user_id' => $user->id]);
        if ($submission->status === AssignmentSubmission::REVIEWED) {
            throw new DomainException('ALREADY_REVIEWED', 'Your instructor has already reviewed this assignment.', 409);
        }
        if ($body === null && $file === null && $submission->file_path === null) {
            throw new DomainException('EMPTY_SUBMISSION', 'Write your answer or attach a file.', 422);
        }

        $oldFile = null;
        if ($file) {
            $oldFile = $submission->file_path;
            $path = $file->storeAs("assignments/{$lecture->id}", Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');
            $submission->fill([
                'file_path' => $path,
                'file_name' => Str::limit($file->getClientOriginalName(), 180, ''),
                'file_size' => $file->getSize(),
            ]);
        }
        $isNew = ! $submission->exists;
        $submission->fill(['body' => $body, 'submitted_at' => now()]);

        DB::transaction(function () use ($submission, $user, $lecture) {
            $submission->save();
            $this->progress->setCompleted($user, $lecture, true);
        });
        if ($oldFile) {
            Storage::disk('local')->delete($oldFile);
        }

        if ($isNew) {
            $lecture->course->instructor->notify(new NewSubmission($submission->load('assignment.lecture.course', 'user')));
        }

        return $submission;
    }

    public function review(User $instructor, AssignmentSubmission $submission, string $feedback): AssignmentSubmission
    {
        $course = $submission->assignment->lecture->course;
        if ($course->instructor_id !== $instructor->id) {
            throw new DomainException('NOT_FOUND', 'Submission not found.', 404);
        }

        $first = $submission->status !== AssignmentSubmission::REVIEWED;
        $submission->forceFill([
            'status' => AssignmentSubmission::REVIEWED,
            'feedback' => trim($feedback),
            'reviewed_by' => $instructor->id,
            'reviewed_at' => now(),
        ])->save();

        if ($first) {
            $submission->user->notify(new SubmissionReviewed($submission));
        }

        return $submission;
    }

    /** The learner who handed it in, the course instructor and course reviewers may open the file. */
    public function canViewFile(User $user, AssignmentSubmission $submission): bool
    {
        if ($submission->user_id === $user->id) {
            return true;
        }
        $role = $this->entitlements->role($user, $submission->assignment->lecture->course);

        return in_array($role, [Entitlements::ROLE_INSTRUCTOR, Entitlements::ROLE_REVIEWER], true);
    }
}
