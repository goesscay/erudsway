<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
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
            /** @var list<array{title: string, duration_seconds: int, lectures: list<array{id: int, title: string, kind: 'video'|'article'|'quiz'|'assignment', duration_seconds: int, is_free_preview: bool}>}> */
            /** The levels this course is sold at, entry level first; empty for a single-price course. */
            /** @var list<array{id: int, name: string, price: array{amount: int, currency: string}, includes: list<string>, is_recommended: bool, access_days: int|null, video_view_limit: int|null, grants_simulator: bool, includes_doubt_session: bool}> */
            'editions' => $this->editions->map(fn (CourseEdition $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'price' => ['amount' => $e->price(), 'currency' => $e->priceTier->currency],
                'includes' => $e->includes,
                'is_recommended' => $e->is_recommended,
                'access_days' => $e->access_days,
                'video_view_limit' => $e->video_view_limit,
                'grants_simulator' => $e->grants_simulator,
                'includes_doubt_session' => $e->includes_doubt_session,
            ])->values()->all(),
            'curriculum' => $this->sections->map(fn (CourseSection $section) => [
                'title' => $section->title,
                'duration_seconds' => (int) $section->lectures->sum('duration_seconds'),
                'lectures' => $section->lectures->map(fn (Lecture $lecture) => [
                    // Used to play free previews; the content itself stays behind playback checks.
                    'id' => $lecture->id,
                    'title' => $lecture->title,
                    'kind' => $lecture->kind->value,
                    'duration_seconds' => $lecture->duration_seconds,
                    'is_free_preview' => $lecture->is_free_preview,
                    /** Name of the edition this lecture starts in, when it isn't in every edition. */
                    'edition' => $lecture->minEdition?->name,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
