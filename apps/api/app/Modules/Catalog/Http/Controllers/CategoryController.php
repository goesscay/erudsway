<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Top-level categories with their subcategories.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()->active()->whereNull('parent_id')->with('children')->orderBy('position')->get();

        return CategoryResource::collection($categories);
    }

    /**
     * One category with its parent and subcategories.
     *
     * @unauthenticated
     */
    public function show(string $slug): CategoryResource
    {
        $category = Category::query()->active()->where('slug', $slug)->with(['parent', 'children'])->firstOrFail();

        return new CategoryResource($category);
    }
}
