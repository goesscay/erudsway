<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Catalog\Enums\CourseLevel;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\InstructorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(4, true));

        return [
            'instructor_id' => fn () => self::instructor()->id,
            'category_id' => fn () => Category::query()->whereNotNull('parent_id')->value('id')
                ?? Category::query()->create([
                    'slug' => 'web-development',
                    'name' => 'Web Development',
                    'parent_id' => Category::query()->create(['slug' => 'development', 'name' => 'Development'])->id,
                ])->id,
            'price_tier_id' => fn () => PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => 49900])->id,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'title' => $title,
            'subtitle' => fake()->sentence(10),
            'description' => '<p>'.fake()->paragraph(4).'</p>',
            'language' => 'English',
            'level' => CourseLevel::Beginner,
            'outcomes' => ['Understand the fundamentals', 'Build a real project'],
            'requirements' => ['A computer with internet access'],
            'audience' => ['Beginners'],
        ];
    }

    public function published(?\DateTimeInterface $at = null): static
    {
        return $this->afterMaking(function (Course $course) use ($at) {
            $course->status = CourseStatus::Published;
            $course->published_at = $at ?? now()->subMonths(3);
        });
    }

    public function free(): static
    {
        return $this->state(['price_tier_id' => null]);
    }

    /** An approved instructor with a public profile. */
    public static function instructor(?string $name = null): User
    {
        $user = User::factory()->withRole(Role::Instructor)->create($name ? ['name' => $name] : []);
        InstructorProfile::query()->create([
            'user_id' => $user->id,
            'slug' => Str::slug($user->name).'-'.$user->id,
            'display_name' => $user->name,
            'headline' => 'Practitioner and instructor',
            'approved_at' => now(),
        ]);

        return $user;
    }
}
