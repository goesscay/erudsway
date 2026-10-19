<?php

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Models\Course;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Swappable search backend. Today: the database (PostgreSQL full-text in production).
 * Later: Meilisearch, without changing controllers or the API contract.
 */
interface CourseSearchEngine
{
    /**
     * @return array{results: LengthAwarePaginator<int, Course>, facets: array<string, mixed>}
     */
    public function search(CourseSearchQuery $query): array;
}
