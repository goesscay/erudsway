<?php

namespace App\Modules\Authoring\Http\Resources;

use App\Modules\Catalog\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row in the instructor's course list.
 *
 * @mixin Course
 */
class InstructorCourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            /** @var 'draft'|'in_review'|'changes_requested'|'published'|'unpublished'|'archived' */
            'status' => $this->status->value,
            'title' => $this->title,
            'thumbnail_url' => $this->thumbnailUrl(),
            'category' => ['slug' => $this->category->slug, 'name' => $this->category->name],
            'lecture_count' => $this->lecture_count,
            'duration_seconds' => $this->duration_seconds,
            'enrollment_count' => $this->enrollment_count,
            'rating' => round($this->rating_avg, 1),
            'rating_count' => $this->rating_count,
            'price' => $this->priceTier ? ['amount' => $this->priceTier->amount, 'currency' => $this->priceTier->currency] : null,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
