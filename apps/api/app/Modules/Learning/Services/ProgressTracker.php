<?php

namespace App\Modules\Learning\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Events\CourseCompleted;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\LectureProgress;
use Illuminate\Support\Facades\DB;

/**
 * Records where learners are. Only enrolled learners have progress; instructors and
 * reviewers previewing a course are not tracked.
 */
class ProgressTracker
{
    /** A video counts as watched once the furthest point reached is this far in. */
    public const COMPLETE_AT = 0.9;

    public function __construct(private readonly Entitlements $entitlements) {}

    public function recordPosition(User $user, Lecture $lecture, int $position): ?LectureProgress
    {
        $enrollment = $this->entitlements->enrollment($user, $lecture->course);
        if ($enrollment === null) {
            return null;
        }

        $duration = max(0, $lecture->duration_seconds);
        $position = max(0, $duration > 0 ? min($position, $duration) : $position);

        return DB::transaction(function () use ($user, $lecture, $enrollment, $position, $duration) {
            $current = $this->find($user, $lecture);
            $max = max($position, $current->max_position_seconds ?? 0);
            $reachedEnd = $lecture->kind === LectureKind::Video && $duration > 0 && $max >= $duration * self::COMPLETE_AT;
            $completedAt = $current->completed_at ?? ($reachedEnd ? now() : null);

            $this->write($user, $lecture, $current !== null, [
                'last_position_seconds' => $position,
                'max_position_seconds' => $max,
                'completed_at' => $completedAt,
            ]);
            $this->touch($enrollment, $lecture, recount: $completedAt !== null && $current?->completed_at === null);

            return $this->find($user, $lecture);
        });
    }

    public function setCompleted(User $user, Lecture $lecture, bool $completed): ?LectureProgress
    {
        $enrollment = $this->entitlements->enrollment($user, $lecture->course);
        if ($enrollment === null) {
            return null;
        }

        return DB::transaction(function () use ($user, $lecture, $enrollment, $completed) {
            $current = $this->find($user, $lecture);
            $this->write($user, $lecture, $current !== null, [
                'last_position_seconds' => $current->last_position_seconds ?? 0,
                'max_position_seconds' => $current->max_position_seconds ?? 0,
                'completed_at' => $completed ? ($current->completed_at ?? now()) : null,
            ]);
            $this->touch($enrollment, $lecture, recount: true);

            return $this->find($user, $lecture);
        });
    }

    /** Recount completed lectures that still exist in the course. */
    public function recount(Enrollment $enrollment): void
    {
        $lectureIds = Lecture::query()->where('course_id', $enrollment->course_id)->pluck('id');
        $total = $lectureIds->count();
        $done = LectureProgress::query()
            ->where('user_id', $enrollment->user_id)
            ->whereIn('lecture_id', $lectureIds)
            ->whereNotNull('completed_at')
            ->count();

        $wasComplete = $enrollment->completed_at !== null;
        $enrollment->forceFill([
            'completed_count' => $done,
            // Floor, so 100% means every lecture is done.
            'progress_percent' => $total > 0 ? intdiv($done * 100, $total) : 0,
            'completed_at' => $total > 0 && $done >= $total ? ($enrollment->completed_at ?? now()) : null,
        ])->save();

        if (! $wasComplete && $enrollment->completed_at !== null) {
            CourseCompleted::dispatch($enrollment);
        }
    }

    private function touch(Enrollment $enrollment, Lecture $lecture, bool $recount): void
    {
        $enrollment->forceFill(['last_lecture_id' => $lecture->id, 'last_accessed_at' => now()]);
        if ($recount) {
            $this->recount($enrollment);
        } else {
            $enrollment->save();
        }
    }

    private function find(User $user, Lecture $lecture): ?LectureProgress
    {
        return LectureProgress::query()->where('user_id', $user->id)->where('lecture_id', $lecture->id)->first();
    }

    /** @param array<string, mixed> $values */
    private function write(User $user, Lecture $lecture, bool $exists, array $values): void
    {
        $key = ['user_id' => $user->id, 'lecture_id' => $lecture->id];
        $table = DB::table('lecture_progress');

        if ($exists) {
            $table->where($key)->update([...$values, 'updated_at' => now()]);
        } else {
            $table->insert([...$key, ...$values, 'course_id' => $lecture->course_id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
