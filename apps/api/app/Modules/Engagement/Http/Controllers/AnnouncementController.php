<?php

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Engagement\Http\Resources\AnnouncementResource;
use App\Modules\Engagement\Models\Announcement;
use App\Modules\Engagement\Services\Announcements;
use App\Modules\Learning\Services\Entitlements;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    /** Announcements in the course player (anyone with access to the course). */
    public function forCourse(Request $request, string $slug, Entitlements $entitlements): AnonymousResourceCollection
    {
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        if ($entitlements->role($this->user($request), $course) === null) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to see its announcements.', 403);
        }

        return AnnouncementResource::collection(Announcement::query()->where('course_id', $course->id)->with('user:id,name')->latest()->get());
    }

    /** Instructor: announcements for one of my courses. */
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        abort_unless($course->instructor_id === $this->user($request)->id, 404);

        return AnnouncementResource::collection(Announcement::query()->where('course_id', $course->id)->with('user:id,name')->latest()->get());
    }

    public function store(Request $request, Course $course, Announcements $announcements): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
        $announcement = $announcements->post($this->user($request), $course, $data['title'], $data['body']);

        return (new AnnouncementResource($announcement->load('user:id,name')))->response()->setStatusCode(201);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
