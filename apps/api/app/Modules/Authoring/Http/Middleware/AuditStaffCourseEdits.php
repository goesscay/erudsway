<?php

namespace App\Modules\Authoring\Http\Middleware;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseSection;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Media\Models\LectureResource;
use App\Modules\Media\Models\UploadSession;
use App\Modules\Platform\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a catalogue manager changes someone else's course in the builder, leave a trail:
 * one audit entry per successful change, naming the builder action. Upload chunks are
 * skipped (the upload's start and completion are recorded instead).
 */
class AuditStaffCourseEdits
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || ! $response->isSuccessful() || $request->routeIs('authoring.uploads.chunk')) {
            return $response;
        }
        /** @var User|null $user */
        $user = $request->user();
        $course = $this->course($request);
        if ($user === null || $course === null || $course->instructor_id === $user->id) {
            return $response;
        }

        $this->audit->record('course.edited_by_staff', $course, null, [
            'action' => str((string) $request->route()?->getName())->after('authoring.')->toString(),
        ]);

        return $response;
    }

    private function course(Request $request): ?Course
    {
        $route = $request->route();
        $param = fn (string $name) => $route?->parameter($name);

        return match (true) {
            $param('course') instanceof Course => $param('course'),
            $param('section') instanceof CourseSection => $param('section')->course,
            $param('lecture') instanceof Lecture => $param('lecture')->course,
            $param('resource') instanceof LectureResource => $param('resource')->lecture->course,
            $param('session') instanceof UploadSession => $param('session')->lecture->course,
            default => null,
        };
    }
}
