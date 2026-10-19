<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Http\Resources\NoteResource;
use App\Modules\Learning\Models\Note;
use App\Modules\Learning\Services\Entitlements;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Private, timestamped notes. Each learner sees only their own. */
class NoteController extends Controller
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function index(Request $request, string $slug): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $this->authorizeCourse($user, $course);

        $notes = Note::query()
            ->where('user_id', $user->id)->where('course_id', $course->id)
            ->with('lecture:id,title')
            ->latest()
            ->get();

        return NoteResource::collection($notes);
    }

    public function store(Request $request, Lecture $lecture): JsonResponse
    {
        $user = $this->user($request);
        $this->authorizeCourse($user, $lecture->course);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'at_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $note = Note::query()->create([
            'user_id' => $user->id,
            'course_id' => $lecture->course_id,
            'lecture_id' => $lecture->id,
            'at_seconds' => min((int) ($data['at_seconds'] ?? 0), max(0, $lecture->duration_seconds)),
            'body' => $data['body'],
        ]);

        return (new NoteResource($note->load('lecture:id,title')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Note $note): NoteResource
    {
        $this->authorizeNote($request, $note);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $note->update(['body' => $data['body']]);

        return new NoteResource($note->load('lecture:id,title'));
    }

    public function destroy(Request $request, Note $note): Response
    {
        $this->authorizeNote($request, $note);
        $note->delete();

        return response()->noContent();
    }

    private function authorizeCourse(User $user, Course $course): void
    {
        if (! $this->entitlements->canAccessCourse($user, $course)) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to take notes.', 403);
        }
    }

    private function authorizeNote(Request $request, Note $note): void
    {
        abort_unless($note->user_id === $this->user($request)->id, 404);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
