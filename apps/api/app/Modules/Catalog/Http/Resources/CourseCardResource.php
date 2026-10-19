<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact course representation for lists and cards.
 *
 * @mixin Course
 */
class CourseCardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'thumbnail_url' => $this->thumbnailUrl(),
            'instructor' => [
                'name' => $this->instructorProfile->display_name ?? $this->instructor->name,
                'slug' => $this->instructorProfile?->slug,
            ],
            'category' => ['slug' => $this->category->slug, 'name' => $this->category->name],
            'level' => $this->level->value,
            'language' => $this->language,
            'rating' => round($this->rating_avg, 1),
            'rating_count' => $this->rating_count,
            'enrollment_count' => $this->enrollment_count,
            'duration_seconds' => $this->duration_seconds,
            'lecture_count' => $this->lecture_count,
            /** Null when the course is free. Amount in paise. */
            'price' => $this->priceTier ? ['amount' => $this->priceTier->amount, 'currency' => $this->priceTier->currency] : null,
            /** @var 'bestseller'|'highest_rated'|'new'|null */
            'badge' => $this->badge(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
