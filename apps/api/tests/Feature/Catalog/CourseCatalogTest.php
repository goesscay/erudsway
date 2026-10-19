<?php

use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use Database\Factories\CourseFactory;

function catalogCategories(): array
{
    $dev = Category::query()->create(['slug' => 'development', 'name' => 'Development']);
    $web = Category::query()->create(['slug' => 'web-development', 'name' => 'Web Development', 'parent_id' => $dev->id]);
    $finance = Category::query()->create(['slug' => 'finance', 'name' => 'Finance']);
    $tax = Category::query()->create(['slug' => 'taxation', 'name' => 'Taxation', 'parent_id' => $finance->id]);

    return compact('dev', 'web', 'finance', 'tax');
}

describe('listing', function () {
    it('shows only published courses', function () {
        Course::factory()->published()->create(['title' => 'Visible course']);
        Course::factory()->create(['title' => 'Draft course']);

        $this->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Visible course')
            ->assertJsonPath('meta.total', 1);
    });

    it('is public and cacheable for guests', function () {
        Course::factory()->published()->create();

        $response = $this->getJson('/api/v1/courses')->assertOk();

        expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=60');
    });

    it('filters by top-level category, including its subcategories', function () {
        ['dev' => $dev, 'web' => $web, 'tax' => $tax] = catalogCategories();
        Course::factory()->published()->create(['category_id' => $web->id, 'title' => 'React basics']);
        Course::factory()->published()->create(['category_id' => $tax->id, 'title' => 'GST basics']);

        $this->getJson('/api/v1/courses?category=development')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'React basics');
    });

    it('returns 404 for an unknown category', function () {
        $this->getJson('/api/v1/courses?category=nope')->assertNotFound();
    });

    it('filters by level, language, rating, price and duration', function () {
        $cheap = PriceTier::query()->create(['amount' => 39900]);
        Course::factory()->published()->create(['title' => 'A', 'level' => CourseLevel::Beginner, 'language' => 'Hindi'])
            ->forceFill(['rating_avg' => 4.6, 'duration_seconds' => 2 * 3600, 'price_tier_id' => $cheap->id])->save();
        Course::factory()->published()->free()->create(['title' => 'B', 'level' => CourseLevel::Advanced])
            ->forceFill(['rating_avg' => 3.9, 'duration_seconds' => 20 * 3600])->save();

        $titles = fn (string $qs) => collect($this->getJson("/api/v1/courses?{$qs}")->assertOk()->json('data'))->pluck('title')->all();

        expect($titles('level=beginner'))->toBe(['A'])
            ->and($titles('level=beginner,advanced'))->toHaveCount(2)
            ->and($titles('language=Hindi'))->toBe(['A'])
            ->and($titles('rating=4.5'))->toBe(['A'])
            ->and($titles('price=free'))->toBe(['B'])
            ->and($titles('price=paid'))->toBe(['A'])
            ->and($titles('duration=short'))->toBe(['A'])
            ->and($titles('duration=extra_long'))->toBe(['B']);
    });

    it('sorts by popularity, rating, newest and price', function () {
        $low = PriceTier::query()->create(['amount' => 39900]);
        $high = PriceTier::query()->create(['amount' => 99900]);
        Course::factory()->published(now()->subDays(100))->create(['title' => 'Old popular', 'price_tier_id' => $high->id])
            ->forceFill(['enrollment_count' => 900, 'rating_avg' => 4.1])->save();
        Course::factory()->published(now()->subDay())->create(['title' => 'New top rated', 'price_tier_id' => $low->id])
            ->forceFill(['enrollment_count' => 10, 'rating_avg' => 4.9])->save();
        Course::factory()->published(now()->subDays(50))->free()->create(['title' => 'Free one'])
            ->forceFill(['enrollment_count' => 300, 'rating_avg' => 4.5])->save();

        $first = fn (string $sort) => $this->getJson("/api/v1/courses?sort={$sort}")->json('data.0.title');

        expect($first('popular'))->toBe('Old popular')
            ->and($first('rating'))->toBe('New top rated')
            ->and($first('newest'))->toBe('New top rated')
            ->and($first('price_asc'))->toBe('Free one')
            ->and($first('price_desc'))->toBe('Old popular');
    });

    it('searches title, description and instructor name', function () {
        Course::factory()->published()->create(['title' => 'GST Returns Masterclass']);
        Course::factory()->published()->create(['title' => 'Something else', 'description' => '<p>Covers input tax credit in depth</p>']);
        $byPriya = Course::factory()->published()->create([
            'title' => 'Bookkeeping',
            'instructor_id' => CourseFactory::instructor('Priya Venkatesh')->id,
        ]);

        $titles = fn (string $q) => collect($this->getJson('/api/v1/courses?q='.urlencode($q))->json('data'))->pluck('title')->all();

        expect($titles('gst returns'))->toBe(['GST Returns Masterclass'])
            ->and($titles('input tax'))->toBe(['Something else'])
            ->and($titles('venkatesh'))->toBe([$byPriya->title])
            ->and($titles('nothing-matches-this'))->toBe([]);
    });

    it('returns facet counts for the current search', function () {
        Course::factory()->published()->count(2)->create(['level' => CourseLevel::Beginner]);
        Course::factory()->published()->free()->create(['level' => CourseLevel::Advanced, 'language' => 'Tamil']);

        $facets = $this->getJson('/api/v1/courses?level=beginner')->assertOk()->json('meta.facets');

        // Facets ignore the level filter itself so the other options stay visible.
        expect(collect($facets['levels'])->firstWhere('value', 'beginner')['count'])->toBe(2)
            ->and(collect($facets['levels'])->firstWhere('value', 'advanced')['count'])->toBe(1)
            ->and(collect($facets['prices'])->firstWhere('value', 'free')['count'])->toBe(1)
            ->and(collect($facets['languages'])->firstWhere('value', 'Tamil')['count'])->toBe(1)
            ->and($facets['total'])->toBe(3);
    });

    it('paginates', function () {
        Course::factory()->published()->count(5)->create();

        $this->getJson('/api/v1/courses?per_page=2&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.last_page', 3);
    });

    it('validates query parameters', function () {
        $this->getJson('/api/v1/courses?sort=cheapest&level=expert&per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('sort', 'error.fields')
            ->assertJsonValidationErrorFor('level.0', 'error.fields')
            ->assertJsonValidationErrorFor('per_page', 'error.fields');
    });
});

describe('course detail', function () {
    it('returns the landing page data with curriculum', function () {
        $course = Course::factory()->published()->create(['title' => 'React from scratch']);
        $section = $course->sections()->create(['title' => 'Basics', 'position' => 1]);
        $section->lectures()->create(['course_id' => $course->id, 'title' => 'Welcome', 'kind' => 'video', 'position' => 1, 'duration_seconds' => 300, 'is_free_preview' => true]);
        $section->lectures()->create(['course_id' => $course->id, 'title' => 'Quiz', 'kind' => 'quiz', 'position' => 2, 'duration_seconds' => 120]);

        $this->getJson("/api/v1/courses/{$course->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', 'React from scratch')
            ->assertJsonPath('data.curriculum.0.title', 'Basics')
            ->assertJsonPath('data.curriculum.0.duration_seconds', 420)
            ->assertJsonPath('data.curriculum.0.lectures.0.is_free_preview', true)
            ->assertJsonPath('data.curriculum.0.lectures.1.kind', 'quiz')
            ->assertJsonPath('data.price.amount', 49900);
    });

    it('hides unpublished courses', function () {
        $draft = Course::factory()->create();

        $this->getJson("/api/v1/courses/{$draft->slug}")->assertNotFound();
    });

    it('builds a breadcrumb from the category tree', function () {
        ['web' => $web] = catalogCategories();
        $course = Course::factory()->published()->create(['category_id' => $web->id]);

        $this->getJson("/api/v1/courses/{$course->slug}")
            ->assertJsonPath('data.breadcrumb', [
                ['slug' => 'development', 'name' => 'Development'],
                ['slug' => 'web-development', 'name' => 'Web Development'],
            ]);
    });
});

describe('badges', function () {
    it('labels bestsellers, highest rated and new courses', function () {
        config(['catalog.badges.bestseller_min_enrollments' => 1000]);

        $best = Course::factory()->published()->create();
        $best->forceFill(['enrollment_count' => 5000])->save();
        $rated = Course::factory()->published()->create();
        $rated->forceFill(['rating_avg' => 4.8, 'rating_count' => 120])->save();
        $new = Course::factory()->published(now()->subDays(3))->create();
        $plain = Course::factory()->published(now()->subYear())->create();

        expect($best->fresh()->badge())->toBe('bestseller')
            ->and($rated->fresh()->badge())->toBe('highest_rated')
            ->and($new->fresh()->badge())->toBe('new')
            ->and($plain->fresh()->badge())->toBeNull();
    });
});

describe('categories and instructors', function () {
    it('lists the category tree', function () {
        catalogCategories();

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.children.0.slug', 'web-development');
    });

    it('shows a category with its parent', function () {
        catalogCategories();

        $this->getJson('/api/v1/categories/taxation')
            ->assertOk()
            ->assertJsonPath('data.parent.slug', 'finance');
    });

    it('shows an instructor profile with their published courses and totals', function () {
        $instructor = CourseFactory::instructor('Kavya Iyer');
        Course::factory()->published()->create(['instructor_id' => $instructor->id])
            ->forceFill(['enrollment_count' => 100, 'rating_avg' => 4.0, 'rating_count' => 10])->save();
        Course::factory()->published()->create(['instructor_id' => $instructor->id])
            ->forceFill(['enrollment_count' => 50, 'rating_avg' => 5.0, 'rating_count' => 30])->save();
        Course::factory()->create(['instructor_id' => $instructor->id]); // draft, excluded

        $slug = $instructor->instructorProfile->slug;

        $this->getJson("/api/v1/instructors/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Kavya Iyer')
            ->assertJsonPath('data.stats.courses', 2)
            ->assertJsonPath('data.stats.learners', 150)
            ->assertJsonPath('data.stats.rating', 4.8) // (4*10 + 5*30) / 40 = 4.75
            ->assertJsonCount(2, 'data.courses');
    });
});
