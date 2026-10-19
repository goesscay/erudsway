<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseSection;
use App\Modules\Catalog\Models\Lecture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the public course landing page needs. Lecture titles and lengths are
 * public (as on any marketplace); lecture content is not.
 *
 * @mixin Course
 */
class CourseDetailResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->instructorProfile;
        $parent = $this->category->parent;

        return [
            ...(new CourseCardResource($this->resource))->toArray($request),
            'description' => $this->description,
            'outcomes' => $this->outcomes,
            'requirements' => $this->requirements,
            'audience' => $this->audience,
            'published_at' => $this->published_at?->toIso8601String(),
            /** @var list<array{slug: string, name: string}> */
            'breadcrumb' => array_values(array_filter([
                $parent ? ['slug' => $parent->slug, 'name' => $parent->name] : null,
                ['slug' => $this->category->slug, 'name' => $this->category->name],
            ])),
            'instructor' => [
                'name' => $profile->display_name ?? $this->instructor->name,
                'slug' => $profile?->slug,
                'headline' => $profile?->headline,
                'bio' => $profile?->bio,
            ],
            /** @var list<array{title: string, duration_seconds: int, lectures: list<array{title: string, kind: 'video'|'article'|'quiz'|'assignment', duration_seconds: int, is_free_preview: bool}>}> */
            'curriculum' => $this->sections->map(fn (CourseSection $section) => [
                'title' => $section->title,
                'duration_seconds' => (int) $section->lectures->sum('duration_seconds'),
                'lectures' => $section->lectures->map(fn (Lecture $lecture) => [
                    'title' => $lecture->title,
                    'kind' => $lecture->kind->value,
                    'duration_seconds' => $lecture->duration_seconds,
                    'is_free_preview' => $lecture->is_free_preview,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
