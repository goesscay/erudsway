<?php

namespace App\Modules\Learning\Http\Resources;

use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseSection;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\LectureProgress;
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
                'progress_percent' => $this->enrollment->progress_percent,
                'completed_count' => $this->enrollment->completed_count,
                'last_lecture_id' => $this->enrollment->last_lecture_id,
                'completed_at' => $this->enrollment->completed_at?->toIso8601String(),
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
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
