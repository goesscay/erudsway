<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Learning\Http\Resources\EnrollmentResource;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Services\Enroller;
use App\Modules\Learning\Services\Entitlements;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EnrollmentController extends Controller
{
    /** My Learning: active courses, most recently studied first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $enrollments = Enrollment::query()->active()
            ->where('user_id', $this->user($request)->id)
            ->with(['course.instructor:id,name', 'course.instructorProfile'])
            ->orderByRaw('COALESCE(last_accessed_at, granted_at) DESC')
            ->get();

        return EnrollmentResource::collection($enrollments);
    }

    /** Whether the signed-in user can open this course in the player (for course page buttons). */
    public function status(Request $request, string $slug, Entitlements $entitlements): JsonResponse
    {
        $user = $this->user($request);
        $course = Course::query()->published()->where('slug', $slug)->firstOrFail();
        $enrollment = $entitlements->enrollment($user, $course);

        return ApiResponse::data([
            'enrolled' => $enrollment !== null,
            'role' => $entitlements->role($user, $course),
            'progress_percent' => $enrollment->progress_percent ?? 0,
        ]);
    }

    /** Enroll in a free course. Idempotent. */
    public function store(Request $request, string $slug, Enroller $enroller): JsonResponse
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $enrollment = $enroller->enrollFree($this->user($request), $course);

        return (new EnrollmentResource($enrollment->load(['course.instructor:id,name', 'course.instructorProfile'])))
            ->response()->setStatusCode($enrollment->wasRecentlyCreated ? 201 : 200);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
