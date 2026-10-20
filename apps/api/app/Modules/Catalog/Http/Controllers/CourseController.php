<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\SearchCoursesRequest;
use App\Modules\Catalog\Http\Resources\CourseCardResource;
use App\Modules\Catalog\Http\Resources\CourseDetailResource;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Search\CourseSearchEngine;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * Search and browse published courses.
     *
     * `meta.facets` gives counts for each filter option, based on the search text and category.
     *
     * @unauthenticated
     */
    public function index(SearchCoursesRequest $request, CourseSearchEngine $search): AnonymousResourceCollection
    {
        $category = null;
        if ($request->filled('category')) {
            $category = Category::query()->active()->where('slug', $request->validated('category'))->with('children')->firstOrFail();
        }

        ['results' => $results, 'facets' => $facets] = $search->search($request->toQuery($category));

        return CourseCardResource::collection($results)->additional(['meta' => ['facets' => $facets]]);
    }

    /**
     * A published course's landing page data.
     *
     * @unauthenticated
     */
    public function show(string $slug): CourseDetailResource
    {
        $course = Course::query()
            ->published()
            ->where('slug', $slug)
            ->with(['instructor:id,name', 'instructorProfile', 'category.parent', 'priceTier', 'editions.priceTier', 'sections.lectures.minEdition'])
            ->firstOrFail();

        return new CourseDetailResource($course);
    }
}
