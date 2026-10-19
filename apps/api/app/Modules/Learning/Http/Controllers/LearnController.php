<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Http\Resources\LearnCourseResource;
use App\Modules\Learning\Http\Resources\LectureContentResource;
use App\Modules\Learning\Models\LectureProgress;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Media\Models\LectureResource;
use App\Support\Http\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearnController extends Controller
{
    public function __construct(private readonly Entitlements $entitlements) {}

    /**
     * The course player outline. Open to enrolled learners, the course's instructor and
     * course reviewers; others get NOT_ENROLLED (live course) or a 404.
     */
    public function show(Request $request, string $slug): LearnCourseResource
    {
        $user = $this->user($request);
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $role = $this->entitlements->role($user, $course);

        if ($role === null) {
            if ($course->status === CourseStatus::Published) {
                throw new DomainException('NOT_ENROLLED', 'Enroll in this course to start learning.', 403);
            }
            abort(404);
        }

        $course->load([
            'instructor:id,name', 'instructorProfile',
            'sections.lectures' => fn ($q) => $q->withCount('resources')->with('videoAsset:id,status'),
        ]);
        $progress = LectureProgress::query()
            ->where('user_id', $user->id)->where('course_id', $course->id)
            ->get()->keyBy('lecture_id');

        return new LearnCourseResource($course, $role, $this->entitlements->enrollment($user, $course), $progress);
    }

    /** One lecture's article text and downloadable files. */
    public function lecture(Request $request, Lecture $lecture): LectureContentResource
    {
        $this->authorizeLecture($request->user(), $lecture);

        return new LectureContentResource($lecture->load('resources'));
    }

    public function download(Request $request, LectureResource $resource): StreamedResponse
    {
        $this->authorizeLecture($request->user(), $resource->lecture);

        return Storage::disk('local')->download($resource->path, $resource->original_name);
    }

    private function authorizeLecture(?User $user, Lecture $lecture): void
    {
        if (! $this->entitlements->canView($user, $lecture)) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to open this lecture.', 403);
        }
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
