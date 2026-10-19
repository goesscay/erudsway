<?php

namespace App\Modules\Catalog\Search;

use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL: weighted full-text search (`search_vector`) plus trigram similarity on titles
 * for typos, ranked by relevance. Other drivers (SQLite in local tests): every word must
 * appear in the title, subtitle, description or instructor name.
 */
class DatabaseCourseSearch implements CourseSearchEngine
{
    public function search(CourseSearchQuery $query): array
    {
        $base = Course::query()->published();
        $this->applyText($base, $query);
        if ($query->category) {
            $base->whereIn('courses.category_id', $query->category->selfAndChildIds());
        }

        // Facet counts reflect the search and category, not the other filters,
        // so learners can see what each filter option would give them.
        $facets = $this->facets($base);

        $filtered = (clone $base)
            ->with(['instructor:id,name', 'instructorProfile', 'category', 'priceTier'])
            ->when($query->levels, fn (Builder $q, array $levels) => $q->whereIn('courses.level', $levels))
            ->when($query->languages, fn (Builder $q, array $langs) => $q->whereIn('courses.language', $langs))
            ->when($query->minRating, fn (Builder $q, float $r) => $q->where('courses.rating_avg', '>=', $r))
            ->when($query->price === 'free', fn (Builder $q) => $q->whereNull('courses.price_tier_id'))
            ->when($query->price === 'paid', fn (Builder $q) => $q->whereNotNull('courses.price_tier_id'))
            ->when($query->duration, fn (Builder $q, string $bucket) => $this->applyDuration($q, $bucket));

        $this->applySort($filtered, $query);

        return [
            'results' => $filtered->paginate($query->perPage, ['courses.*'], 'page', $query->page),
            'facets' => $facets,
        ];
    }

    /** @param Builder<Course> $q */
    private function applyText(Builder $q, CourseSearchQuery $query): void
    {
        if (! $query->hasText()) {
            return;
        }
        $text = mb_substr(trim((string) $query->text), 0, 100);

        if ($this->isPostgres()) {
            $q->where(function (Builder $w) use ($text) {
                $w->whereRaw("courses.search_vector @@ websearch_to_tsquery('english', ?)", [$text])
                    ->orWhereRaw('lower(courses.title) % lower(?)', [$text])
                    ->orWhereHas('instructorProfile', fn (Builder $p) => $p->where('display_name', 'ilike', '%'.$this->escapeLike($text).'%'));
            });

            return;
        }

        // Fallback for SQLite (local dev/tests only). SQLite has no default LIKE escape
        // character, so wildcards typed by the user just widen the match here.
        foreach (preg_split('/\s+/', mb_strtolower($text)) ?: [] as $word) {
            $like = '%'.$word.'%';
            $q->where(function (Builder $w) use ($like) {
                $w->whereRaw('lower(courses.title) like ?', [$like])
                    ->orWhereRaw('lower(courses.subtitle) like ?', [$like])
                    ->orWhereRaw('lower(courses.description) like ?', [$like])
                    ->orWhereHas('instructorProfile', fn (Builder $p) => $p->whereRaw('lower(display_name) like ?', [$like]));
            });
        }
    }

    /** @param Builder<Course> $q */
    private function applySort(Builder $q, CourseSearchQuery $query): void
    {
        $sort = $query->sort === 'relevance' && ! $query->hasText() ? 'popular' : $query->sort;

        match ($sort) {
            'relevance' => $this->isPostgres()
                ? $q->orderByRaw(
                    "ts_rank_cd(courses.search_vector, websearch_to_tsquery('english', ?)) + similarity(lower(courses.title), lower(?)) desc",
                    [$query->text, $query->text],
                )->orderByDesc('courses.enrollment_count')
                : $q->orderByDesc('courses.enrollment_count'),
            'rating' => $q->orderByDesc('courses.rating_avg')->orderByDesc('courses.rating_count'),
            'newest' => $q->orderByDesc('courses.published_at'),
            'price_asc', 'price_desc' => $q
                ->leftJoin('price_tiers', 'price_tiers.id', '=', 'courses.price_tier_id')
                ->orderByRaw('coalesce(price_tiers.amount, 0) '.($sort === 'price_asc' ? 'asc' : 'desc')),
            // The default browse order (homepage, category pages): pinned courses first, newest pin on top.
            default => $q->orderByRaw('case when courses.featured_at is null then 1 else 0 end')
                ->orderByDesc('courses.featured_at')
                ->orderByDesc('courses.enrollment_count'),
        };

        $q->orderBy('courses.id'); // stable pagination
    }

    /** @param Builder<Course> $q */
    private function applyDuration(Builder $q, string $bucket): void
    {
        [$min, $max] = config("catalog.duration_buckets.{$bucket}");
        $q->where('courses.duration_seconds', '>=', $min * 3600);
        if ($max !== null) {
            $q->where('courses.duration_seconds', '<', $max * 3600);
        }
    }

    /**
     * @param  Builder<Course>  $base
     * @return array<string, mixed>
     */
    private function facets(Builder $base): array
    {
        $count = fn (string $column) => (clone $base)->reorder()
            ->select("courses.{$column} as value", DB::raw('count(*) as count'))
            ->groupBy("courses.{$column}")
            ->pluck('count', 'value');

        $levels = $count('level');
        $languages = $count('language');

        $ratings = [];
        foreach ([4.5, 4.0, 3.5, 3.0] as $min) {
            $ratings[] = ['value' => $min, 'count' => (clone $base)->where('courses.rating_avg', '>=', $min)->count()];
        }

        $durations = [];
        foreach (array_keys(config('catalog.duration_buckets')) as $bucket) {
            $q = clone $base;
            $this->applyDuration($q, $bucket);
            $durations[] = ['value' => $bucket, 'count' => $q->count()];
        }

        $free = (clone $base)->whereNull('courses.price_tier_id')->count();
        $total = (clone $base)->count();

        return [
            'levels' => collect(CourseLevel::cases())
                ->map(fn (CourseLevel $l) => ['value' => $l->value, 'label' => $l->label(), 'count' => (int) ($levels[$l->value] ?? 0)])
                ->values()->all(),
            'languages' => $languages->map(fn ($c, $v) => ['value' => (string) $v, 'count' => (int) $c])
                ->sortByDesc('count')->values()->all(),
            'ratings' => $ratings,
            'durations' => $durations,
            'prices' => [
                ['value' => 'free', 'count' => $free],
                ['value' => 'paid', 'count' => $total - $free],
            ],
            'total' => $total,
        ];
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
