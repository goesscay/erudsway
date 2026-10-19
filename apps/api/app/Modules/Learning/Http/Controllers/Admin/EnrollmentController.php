<?php

namespace App\Modules\Learning\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Learning\Http\Resources\EnrollmentResource;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Services\Enroller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Course access granted or removed by staff (scholarships, corporate batches, support
 * fixes). Requires `manage_users`; every change is audited and needs a reason.
 */
class EnrollmentController extends Controller
{
    public function index(User $user): AnonymousResourceCollection
    {
        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with(['course.instructor:id,name', 'course.instructorProfile'])
            ->latest('granted_at')
            ->get();

        return EnrollmentResource::collection($enrollments);
    }

    public function store(Request $request, User $user, Enroller $enroller): JsonResponse
    {
        $data = $request->validate([
            'course' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $course = Course::query()->where('slug', $data['course'])->first()
            ?? throw ValidationException::withMessages(['course' => 'Choose one of the listed courses.']);

        $enrollment = $enroller->grant($user, $course, $this->admin($request), $data['reason']);

        return (new EnrollmentResource($enrollment->load(['course.instructor:id,name', 'course.instructorProfile'])))
            ->response()->setStatusCode(201);
    }

    public function revoke(Request $request, Enrollment $enrollment, Enroller $enroller): EnrollmentResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $enrollment = $enroller->revoke($enrollment, $this->admin($request), $data['reason']);

        return new EnrollmentResource($enrollment->load(['course.instructor:id,name', 'course.instructorProfile']));
    }

    private function admin(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
