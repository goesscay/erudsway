<?php

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Models\Category;

final readonly class CourseSearchQuery
{
    /**
     * @param  list<string>  $levels
     * @param  list<string>  $languages
     */
    public function __construct(
        public ?string $text = null,
        public ?Category $category = null,
        public array $levels = [],
        public array $languages = [],
        public ?float $minRating = null,
        public ?string $price = null,      // free | paid
        public ?string $duration = null,   // key of catalog.duration_buckets
        public string $sort = 'relevance', // relevance | popular | rating | newest | price_asc | price_desc
        public int $perPage = 20,
        public int $page = 1,
    ) {}

    public function hasText(): bool
    {
        return $this->text !== null && trim($this->text) !== '';
    }
}
