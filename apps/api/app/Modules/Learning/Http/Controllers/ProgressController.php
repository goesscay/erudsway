<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Models\LectureProgress;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Learning\Services\ProgressTracker;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Learner progress. Instructors and reviewers previewing a course get a 200 with
 * `tracked: false`, so the player behaves the same for everyone.
 */
class ProgressController extends Controller
{
    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly ProgressTracker $tracker,
    ) {}

    /** Save the playback position (the player calls this every ~15 s and on pause). */
    public function update(Request $request, Lecture $lecture): JsonResponse
    {
        $data = $request->validate(['position_seconds' => ['required', 'integer', 'min:0', 'max:86400']]);
        $user = $this->authorize($request, $lecture);

        return $this->respond($user, $lecture, $this->tracker->recordPosition($user, $lecture, $data['position_seconds']));
    }

    /** Tick a lecture as done (articles, or a video the learner skimmed). */
    public function complete(Request $request, Lecture $lecture): JsonResponse
    {
        $user = $this->authorize($request, $lecture);
        $this->manual($lecture);

        return $this->respond($user, $lecture, $this->tracker->setCompleted($user, $lecture, true));
    }

    public function uncomplete(Request $request, Lecture $lecture): JsonResponse
    {
        $user = $this->authorize($request, $lecture);
        $this->manual($lecture);

        return $this->respond($user, $lecture, $this->tracker->setCompleted($user, $lecture, false));
    }

    /** Quizzes complete when passed and assignments when handed in, never by a tick. */
    private function manual(Lecture $lecture): void
    {
        if (in_array($lecture->kind, [LectureKind::Quiz, LectureKind::Assignment], true)) {
            throw new DomainException('COMPLETES_AUTOMATICALLY', $lecture->kind === LectureKind::Quiz
                ? 'This quiz completes when you pass it.'
                : 'This assignment completes when you hand it in.', 422);
        }
    }

    private function authorize(Request $request, Lecture $lecture): User
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->entitlements->canAccessCourse($user, $lecture->course)) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to track progress.', 403);
        }
        if ($needed = $this->entitlements->lockedBehind($user, $lecture)) {
            throw Entitlements::lockedError($needed);
        }

        return $user;
    }

    private function respond(User $user, Lecture $lecture, ?LectureProgress $progress): JsonResponse
    {
        $enrollment = $progress ? $this->entitlements->enrollment($user, $lecture->course) : null;

        return ApiResponse::data([
            'lecture_id' => $lecture->id,
            'tracked' => $progress !== null,
            'completed' => $progress?->completed_at !== null,
            'last_position_seconds' => $progress->last_position_seconds ?? 0,
            /** Full views left under the edition's limit; null = unlimited. The player stops at 0. */
            'views_left' => $enrollment ? $this->tracker->viewsLeft($enrollment, $lecture) : null,
            'enrollment' => $enrollment ? [
                'progress_percent' => $enrollment->progress_percent,
                'completed_count' => $enrollment->completed_count,
            ] : null,
        ]);
    }
}
