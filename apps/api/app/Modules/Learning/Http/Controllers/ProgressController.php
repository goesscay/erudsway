<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        return $this->respond($user, $lecture, $this->tracker->setCompleted($user, $lecture, true));
    }

    public function uncomplete(Request $request, Lecture $lecture): JsonResponse
    {
        $user = $this->authorize($request, $lecture);

        return $this->respond($user, $lecture, $this->tracker->setCompleted($user, $lecture, false));
    }

    private function authorize(Request $request, Lecture $lecture): User
    {
        /** @var User $user */
        $user = $request->user();
        if (! $this->entitlements->canAccessCourse($user, $lecture->course)) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to track progress.', 403);
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
            'enrollment' => $enrollment ? [
                'progress_percent' => $enrollment->progress_percent,
                'completed_count' => $enrollment->completed_count,
            ] : null,
        ]);
    }
}
