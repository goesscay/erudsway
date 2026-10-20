<?php

namespace App\Modules\Learning\Http\Resources;

use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Models\CourseSection;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Services\Editions;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\LectureProgress;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Learning\Services\ProgressTracker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Everything the course player needs up front: the outline with the viewer's progress.
 * Lecture content (video, article, files) is fetched per lecture.
 *
 * @mixin Course
 */
class LearnCourseResource extends JsonResource
{
    /** @param Collection<int, LectureProgress> $progress keyed by lecture id */
    public function __construct(
        Course $course,
        private readonly string $role,
        private readonly ?Enrollment $enrollment,
        private readonly Collection $progress,
    ) {
        parent::__construct($course);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->instructorProfile;

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'thumbnail_url' => $this->thumbnailUrl(),
            'lecture_count' => $this->lecture_count,
            'duration_seconds' => $this->duration_seconds,
            'is_published' => $this->status === CourseStatus::Published,
            /** @var 'learner'|'instructor'|'reviewer' */
            'role' => $this->role,
            'instructor' => [
                'name' => $profile->display_name ?? $this->instructor->name,
                'slug' => $profile?->slug,
                'headline' => $profile?->headline,
            ],
            'enrollment' => $this->enrollment ? [
                /** @var array{id: int, name: string}|null */
                'edition' => $this->enrollment->edition ? ['id' => $this->enrollment->edition->id, 'name' => $this->enrollment->edition->name] : null,
                'expires_at' => $this->enrollment->expires_at?->toIso8601String(),
                'progress_percent' => $this->enrollment->progress_percent,
                'completed_count' => $this->enrollment->completed_count,
                'last_lecture_id' => $this->enrollment->last_lecture_id,
                'completed_at' => $this->enrollment->completed_at?->toIso8601String(),
                'certificate_code' => $this->enrollment->certificate?->code,
            ] : null,
            /** @var list<array{id: int, title: string, lectures: list<array{id: int, title: string, kind: 'video'|'article'|'quiz'|'assignment', duration_seconds: int, is_free_preview: bool, has_video: bool, resource_count: int, completed: bool, last_position_seconds: int}>}> */
            'curriculum' => $this->sections->map(fn (CourseSection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'lectures' => $section->lectures->map(fn (Lecture $lecture) => [
                    'id' => $lecture->id,
                    'title' => $lecture->title,
                    'kind' => $lecture->kind->value,
                    'duration_seconds' => $lecture->duration_seconds,
                    'is_free_preview' => $lecture->is_free_preview,
                    'has_video' => (bool) $lecture->videoAsset?->isReady(),
                    'resource_count' => (int) $lecture->getAttribute('resources_count'),
                    'completed' => $this->progress->get($lecture->id)?->completed_at !== null,
                    'last_position_seconds' => $this->progress->get($lecture->id)->last_position_seconds ?? 0,
                    /** Set when the learner's edition doesn't include this lecture. */
                    /** @var array{id: int, name: string}|null */
                    'locked_until' => ($needed = $this->lockFor($lecture)) ? ['id' => $needed->id, 'name' => $needed->name] : null,
                    /** Full views left under the edition's limit; null = unlimited. */
                    'views_left' => $this->viewsLeft($lecture),
                ])->values()->all(),
            ])->values()->all(),
            /** Higher editions the learner can move to, with what the upgrade costs. */
            /** @var list<array{id: int, name: string, includes: list<string>, upgrade_price: int}> */
            'upgrades' => $this->upgrades(),
        ];
    }

    private function lockFor(Lecture $lecture): ?CourseEdition
    {
        $mine = $this->enrollment?->edition;
        if ($this->role !== Entitlements::ROLE_LEARNER || $mine === null || $lecture->is_free_preview || $mine->covers($lecture->minEdition)) {
            return null;
        }

        return $lecture->minEdition;
    }

    private function viewsLeft(Lecture $lecture): ?float
    {
        $limit = $this->enrollment?->edition?->video_view_limit;
        if ($limit === null || $lecture->kind !== LectureKind::Video || $lecture->duration_seconds <= 0) {
            return null;
        }
        $watched = (int) ($this->progress->get($lecture->id)->watched_seconds ?? 0);

        return max(0.0, round($limit - $watched / ($lecture->duration_seconds * ProgressTracker::COMPLETE_AT), 2));
    }

    /** @return list<array{id: int, name: string, includes: list<string>, upgrade_price: int}> */
    private function upgrades(): array
    {
        $mine = $this->enrollment?->edition;
        if ($mine === null) {
            return [];
        }
        $editions = app(Editions::class);

        return $this->editions->filter(fn (CourseEdition $e) => $e->position > $mine->position)->map(fn (CourseEdition $e) => [
            'id' => $e->id,
            'name' => $e->name,
            'includes' => $e->includes,
            'upgrade_price' => (int) $editions->upgradePrice($mine, $e),
        ])->values()->all();
    }
}
