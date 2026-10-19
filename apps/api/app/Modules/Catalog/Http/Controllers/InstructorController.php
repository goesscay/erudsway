<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Resources\CourseCardResource;
use App\Modules\Catalog\Models\Course;
use App\Modules\Identity\Models\InstructorProfile;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class InstructorController extends Controller
{
    /**
     * Public instructor profile with their published courses and totals.
     *
     * @unauthenticated
     */
    public function show(string $slug): JsonResponse
    {
        $profile = InstructorProfile::query()->where('slug', $slug)->firstOrFail();

        $courses = Course::query()
            ->published()
            ->where('instructor_id', $profile->user_id)
            ->with(['instructor:id,name', 'instructorProfile', 'category', 'priceTier'])
            ->orderByDesc('enrollment_count')
            ->get();

        $reviews = (int) $courses->sum('rating_count');
        $weighted = $reviews > 0
            ? $courses->sum(fn (Course $c) => $c->rating_avg * $c->rating_count) / $reviews
            : 0.0;

        return ApiResponse::data([
            'slug' => $profile->slug,
            'name' => $profile->display_name,
            'headline' => $profile->headline,
            'bio' => $profile->bio,
            'stats' => [
                'learners' => (int) $courses->sum('enrollment_count'),
                'reviews' => $reviews,
                'rating' => round($weighted, 1),
                'courses' => $courses->count(),
            ],
            'courses' => CourseCardResource::collection($courses)->resolve(),
        ]);
    }
}
