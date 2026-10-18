<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Actions\ReviewInstructorApplication;
use App\Modules\Identity\Enums\ApplicationStatus;
use App\Modules\Identity\Http\Requests\ReviewApplicationRequest;
use App\Modules\Identity\Http\Resources\InstructorApplicationResource;
use App\Modules\Identity\Models\InstructorApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Review queue for instructor applications.
 * Requires the `review_instructor_applications` permission.
 */
class InstructorApplicationController extends Controller
{
    /** List applications, oldest pending first by default. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ApplicationStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $status = $validated['status'] ?? ApplicationStatus::Pending->value;

        $applications = InstructorApplication::query()
            ->with(['user', 'reviewer'])
            ->where('status', $status)
            ->orderBy($status === ApplicationStatus::Pending->value ? 'created_at' : 'reviewed_at', $status === ApplicationStatus::Pending->value ? 'asc' : 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return InstructorApplicationResource::collection($applications);
    }

    public function show(InstructorApplication $application): InstructorApplicationResource
    {
        return new InstructorApplicationResource($application->load(['user', 'reviewer']));
    }

    public function approve(ReviewApplicationRequest $request, InstructorApplication $application, ReviewInstructorApplication $review): InstructorApplicationResource
    {
        $result = $review->approve($application, $this->reviewer($request), $request->validated('notes'));

        return new InstructorApplicationResource($result->load(['user', 'reviewer']));
    }

    public function reject(ReviewApplicationRequest $request, InstructorApplication $application, ReviewInstructorApplication $review): InstructorApplicationResource
    {
        $result = $review->reject($application, $this->reviewer($request), (string) $request->validated('notes'));

        return new InstructorApplicationResource($result->load(['user', 'reviewer']));
    }

    private function reviewer(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
