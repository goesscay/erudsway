<?php

namespace App\Modules\Learning\Services;

use App\Models\User;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Events\CourseCompleted;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\LectureProgress;
use Illuminate\Support\Collection;
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
            // Time actually watched, for view limits: forward steps of up to a minute between
            // reports count; seeks and jumps back don't.
            $step = $position - ($current->last_position_seconds ?? 0);
            $watched = ($current->watched_seconds ?? 0) + ($step > 0 && $step <= 60 ? $step : 0);
            $reachedEnd = $lecture->kind === LectureKind::Video && $duration > 0 && $max >= $duration * self::COMPLETE_AT;
            $completedAt = $current->completed_at ?? ($reachedEnd ? now() : null);

            $this->write($user, $lecture, $current !== null, [
                'last_position_seconds' => $position,
                'max_position_seconds' => $max,
                'watched_seconds' => $watched,
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

    /**
     * How much of the course the learner has actually consumed, in percent: the higher of
     * the most lectures ever completed and the share of video time watched. Used to decide
     * whether a refund can be approved automatically.
     */
    public function consumedPercent(Enrollment $enrollment): int
    {
        $videos = Lecture::query()->where('course_id', $enrollment->course_id)->where('kind', LectureKind::Video->value)->pluck('duration_seconds', 'id');
        $total = (int) $videos->sum();
        $watched = 0;
        if ($total > 0) {
            $positions = LectureProgress::query()->where('user_id', $enrollment->user_id)->whereIn('lecture_id', $videos->keys())->pluck('max_position_seconds', 'lecture_id');
            foreach ($positions as $lectureId => $seconds) {
                $watched += min((int) $seconds, (int) $videos[$lectureId]);
            }
        }

        return max((int) $enrollment->peak_progress_percent, (int) $enrollment->progress_percent, $total > 0 ? intdiv($watched * 100, $total) : 0);
    }

    /**
     * Full views of a video still available to this learner, or null when unlimited. One
     * view is the video's length in watched time; 90% of it counts as a full view.
     */
    public function viewsLeft(Enrollment $enrollment, Lecture $lecture): ?float
    {
        $limit = $enrollment->edition?->video_view_limit;
        if ($limit === null || $lecture->kind !== LectureKind::Video || $lecture->duration_seconds <= 0) {
            return null;
        }
        $watched = (int) LectureProgress::query()->where('user_id', $enrollment->user_id)->where('lecture_id', $lecture->id)->value('watched_seconds');

        return max(0.0, round($limit - $watched / ($lecture->duration_seconds * self::COMPLETE_AT), 2));
    }

    /**
     * Ids of the course's lectures this enrollment's edition includes.
     *
     * @return Collection<int, int>
     */
    public function includedLectureIds(Enrollment $enrollment): Collection
    {
        $edition = $enrollment->edition;
        $covered = $edition ? CourseEdition::query()->where('course_id', $edition->course_id)->where('position', '<=', $edition->position)->pluck('id') : null;

        return Lecture::query()->where('course_id', $enrollment->course_id)
            ->when($covered, fn ($q) => $q->where(fn ($w) => $w->whereNull('min_edition_id')->orWhereIn('min_edition_id', $covered)))
            ->pluck('id');
    }

    /** Recount completed lectures that still exist in the course and are in the learner's edition. */
    public function recount(Enrollment $enrollment): void
    {
        $lectureIds = $this->includedLectureIds($enrollment);
        $total = $lectureIds->count();
        $done = LectureProgress::query()
            ->where('user_id', $enrollment->user_id)
            ->whereIn('lecture_id', $lectureIds)
            ->whereNotNull('completed_at')
            ->count();

        $wasComplete = $enrollment->completed_at !== null;
        // Floor, so 100% means every lecture is done.
        $percent = $total > 0 ? intdiv($done * 100, $total) : 0;
        $enrollment->forceFill([
            'completed_count' => $done,
            'progress_percent' => $percent,
            'peak_progress_percent' => max($percent, (int) $enrollment->peak_progress_percent),
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
