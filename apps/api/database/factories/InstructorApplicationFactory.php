<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Enums\TeachingExperience;
use App\Modules\Identity\Models\InstructorApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorApplication>
 */
class InstructorApplicationFactory extends Factory
{
    protected $model = InstructorApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'headline' => 'Chartered Accountant and GST trainer',
            'topics' => ['Accounting', 'GST'],
            'experience' => fake()->paragraph(4),
            'teaching_experience' => TeachingExperience::Classroom,
            'sample_url' => 'https://example.com/sample-lesson',
        ];
    }
}
