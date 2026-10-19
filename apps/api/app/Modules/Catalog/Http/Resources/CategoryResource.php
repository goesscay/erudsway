<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'slug' => $this->parent->slug,
                'name' => $this->parent->name,
            ] : null),
            /** @var list<array{slug: string, name: string}> */
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn (Category $child) => [
                'slug' => $child->slug,
                'name' => $child->name,
            ])->values()->all()),
        ];
    }
}
