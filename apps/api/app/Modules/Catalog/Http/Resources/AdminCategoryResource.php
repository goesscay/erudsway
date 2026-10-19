<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category as the admin category screen sees it, hidden ones included.
 *
 * @mixin Category
 */
class AdminCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'course_count' => (int) ($this->courses_count ?? 0),
            'published_count' => (int) ($this->published_courses_count ?? 0),
            /** @var list<AdminCategoryResource> */
            'children' => $this->whenLoaded('allChildren', fn () => AdminCategoryResource::collection($this->allChildren)->resolve($request), []),
        ];
    }
}
