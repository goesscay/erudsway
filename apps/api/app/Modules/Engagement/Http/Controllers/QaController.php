<?php

namespace App\Modules\Engagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Engagement\Http\Resources\QuestionResource;
use App\Modules\Engagement\Models\Answer;
use App\Modules\Engagement\Models\Question;
use App\Modules\Engagement\Services\QuestionsAndAnswers;
use App\Modules\Learning\Services\Entitlements;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QaController extends Controller
{
    public function __construct(
        private readonly QuestionsAndAnswers $qa,
        private readonly Entitlements $entitlements,
    ) {}

    /** A course's questions, most recently active first. Instructors and moderators also see hidden ones. */
    public function index(Request $request, string $slug): AnonymousResourceCollection
    {
        $data = $request->validate([
            'lecture' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'mine' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $role = $this->role($request, $course);

        $questions = Question::query()->where('course_id', $course->id)
            ->when($role === Entitlements::ROLE_LEARNER, fn ($q) => $q->where('status', 'visible'))
            ->when($data['lecture'] ?? null, fn ($q, $id) => $q->where('lecture_id', $id))
            ->when($request->boolean('mine'), fn ($q) => $q->where('user_id', $this->user($request)->id))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('body', 'like', "%{$term}%")))
            ->with(['user:id,name', 'lecture:id,title', 'course:id,slug,title'])
            ->latest('last_activity_at')
            ->paginate(15);

        return QuestionResource::collection($questions);
    }

    public function show(Request $request, Question $question): QuestionResource
    {
        $role = $this->role($request, $question->course);
        if ($role === Entitlements::ROLE_LEARNER && $question->status === 'hidden') {
            abort(404);
        }

        return new QuestionResource($question->load([
            'user:id,name', 'lecture:id,title', 'course:id,slug,title',
            'answers' => fn ($q) => $q->when($role === Entitlements::ROLE_LEARNER, fn ($w) => $w->where('status', 'visible'))->with('user:id,name'),
        ]));
    }

    public function ask(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:8', 'max:160'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'lecture_id' => ['nullable', 'integer'],
        ]);
        $course = Course::query()->where('slug', $slug)->firstOrFail();
        $lecture = isset($data['lecture_id']) ? Lecture::query()->find($data['lecture_id']) : null;

        $question = $this->qa->ask($this->user($request), $course, $lecture, $data['title'], $data['body']);

        return (new QuestionResource($question->load(['user:id,name', 'lecture:id,title', 'course:id,slug,title'])))->response()->setStatusCode(201);
    }

    public function answer(Request $request, Question $question): QuestionResource
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $this->qa->answer($this->user($request), $question->load('course'), $data['body']);

        return $this->show($request, $question->refresh());
    }

    public function hideQuestion(Request $request, Question $question): Response
    {
        $this->qa->setHidden($this->user($request), $question->load('course'), $request->boolean('hidden', true));

        return response()->noContent();
    }

    public function hideAnswer(Request $request, Answer $answer): Response
    {
        $this->qa->setHidden($this->user($request), $answer->load('question.course'), $request->boolean('hidden', true));

        return response()->noContent();
    }

    /** Instructor: questions across my courses, unanswered by me first. */
    public function forInstructor(Request $request): AnonymousResourceCollection
    {
        $request->validate(['unanswered' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1']]);

        return QuestionResource::collection(
            Question::query()
                ->whereHas('course', fn ($q) => $q->where('instructor_id', $this->user($request)->id))
                ->where('status', 'visible')
                ->when($request->boolean('unanswered'), fn ($q) => $q->where('instructor_answered', false))
                ->with(['user:id,name', 'lecture:id,title', 'course:id,slug,title'])
                ->orderBy('instructor_answered')
                ->latest('last_activity_at')
                ->paginate(20),
        );
    }

    private function role(Request $request, Course $course): string
    {
        return $this->entitlements->role($this->user($request), $course)
            ?? throw new DomainException('NOT_ENROLLED', 'Enroll in this course to see its Q&A.', 403);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
