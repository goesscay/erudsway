<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Search\CourseSearchQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCoursesRequest extends FormRequest
{
    /** Accept `level=beginner,advanced` as well as `level[]=beginner`. */
    protected function prepareForValidation(): void
    {
        foreach (['level', 'language'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => array_values(array_filter(explode(',', $this->input($key))))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:80'],
            'level' => ['sometimes', 'array'],
            'level.*' => [Rule::enum(CourseLevel::class)],
            'language' => ['sometimes', 'array'],
            'language.*' => ['string', Rule::in(config('catalog.languages'))],
            'rating' => ['sometimes', 'numeric', Rule::in([3, 3.5, 4, 4.5])],
            'price' => ['sometimes', Rule::in(['free', 'paid'])],
            'duration' => ['sometimes', Rule::in(array_keys(config('catalog.duration_buckets')))],
            'sort' => ['sometimes', Rule::in(['relevance', 'popular', 'rating', 'newest', 'price_asc', 'price_desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function toQuery(?Category $category): CourseSearchQuery
    {
        /** @var list<string> $levels */
        $levels = $this->validated('level', []);
        /** @var list<string> $languages */
        $languages = $this->validated('language', []);

        return new CourseSearchQuery(
            text: $this->validated('q'),
            category: $category,
            levels: $levels,
            languages: $languages,
            minRating: $this->has('rating') ? (float) $this->validated('rating') : null,
            price: $this->validated('price'),
            duration: $this->validated('duration'),
            sort: $this->validated('sort', 'relevance'),
            perPage: (int) $this->validated('per_page', 20),
            page: (int) $this->validated('page', 1),
        );
    }
}
