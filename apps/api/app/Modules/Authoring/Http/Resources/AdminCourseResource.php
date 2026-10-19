<?php

namespace App\Modules\Authoring\Http\Resources;

use App\Modules\Catalog\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the admin course list.
 *
 * @mixin Course
 */
class AdminCourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status->value,
            'thumbnail_url' => $this->thumbnailUrl(),
            'category' => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'parent' => $this->category->parent?->name,
            ],
            'instructor' => [
                'id' => $this->instructor->uuid,
                'name' => $this->instructor->name,
                'email' => $this->instructor->email,
            ],
            'price' => $this->priceTier ? ['amount' => $this->priceTier->amount, 'currency' => $this->priceTier->currency] : null,
            'lecture_count' => $this->lecture_count,
            'enrollment_count' => $this->enrollment_count,
            'rating' => round($this->rating_avg, 1),
            'rating_count' => $this->rating_count,
            'featured' => $this->featured_at !== null,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
