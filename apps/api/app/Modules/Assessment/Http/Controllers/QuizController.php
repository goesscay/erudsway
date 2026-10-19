<?php

namespace App\Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizOption;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Services\Quizzes;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Services\Entitlements;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QuizController extends Controller
{
    public function __construct(private readonly Quizzes $quizzes) {}

    /** Instructor: the quiz with its answers, for the course builder. */
    public function edit(Lecture $lecture): JsonResponse
    {
        Gate::authorize('view', $lecture->course);

        return ApiResponse::data($this->editor($this->quizzes->for($lecture)));
    }

    /** Instructor: replace the quiz's settings and questions. */
    public function update(Request $request, Lecture $lecture): JsonResponse
    {
        Gate::authorize('update', $lecture->course);
        $data = $request->validate([
            'pass_percent' => ['required', 'integer', 'between:0,100'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.type' => ['required', Rule::in([QuizQuestion::SINGLE, QuizQuestion::MULTIPLE])],
            'questions.*.prompt' => ['required', 'string', 'max:2000'],
            'questions.*.explanation' => ['nullable', 'string', 'max:2000'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:10'],
            'questions.*.options.*.id' => ['nullable', 'integer'],
            'questions.*.options.*.body' => ['required', 'string', 'max:500'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
        ]);

        /** @var array{pass_percent: int, shuffle_questions?: bool, questions: list<array{id?: int|null, type: string, prompt: string, explanation?: string|null, options: list<array{id?: int|null, body: string, is_correct: bool}>}>} $data */
        return ApiResponse::data($this->editor($this->quizzes->save($lecture, $data)));
    }

    /** Learner: the quiz to take (no answers), plus how they've done so far. */
    public function show(Request $request, Lecture $lecture, Entitlements $entitlements): JsonResponse
    {
        $user = $this->user($request);
        if ($entitlements->role($user, $lecture->course) === null) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to take its quizzes.', 403);
        }
        $quiz = $this->quizzes->for($lecture);
        $summary = $this->quizzes->summary($user, $quiz);
        $questions = $quiz->shuffle_questions ? $quiz->questions->shuffle() : $quiz->questions;

        return ApiResponse::data([
            'lecture_id' => $lecture->id,
            'pass_percent' => $quiz->pass_percent,
            /** @var list<array{id: int, type: 'single'|'multiple', prompt: string, options: list<array{id: int, body: string}>}> */
            'questions' => $questions->map(fn (QuizQuestion $q) => [
                'id' => $q->id,
                'type' => $q->type,
                'prompt' => $q->prompt,
                'options' => $q->options->map(fn (QuizOption $o) => ['id' => $o->id, 'body' => $o->body])->values()->all(),
            ])->values()->all(),
            'attempts' => [
                'count' => $summary['count'],
                'best_percent' => $summary['best_percent'],
                'passed' => $summary['passed'],
            ],
            'last_result' => $summary['last'] ? $this->result($quiz, $summary['last'], true, $summary['passed']) : null,
        ]);
    }

    /** Learner: hand in answers; graded at once. */
    public function submit(Request $request, Lecture $lecture): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['present', 'array', 'max:100'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option_ids' => ['present', 'array', 'max:10'],
            'answers.*.option_ids.*' => ['integer'],
        ]);
        $answers = [];
        foreach ($data['answers'] as $a) {
            $answers[(int) $a['question_id']] = array_map('intval', $a['option_ids']);
        }

        $graded = $this->quizzes->submit($this->user($request), $lecture, $answers);

        $quiz = $this->quizzes->for($lecture);
        // Learners see the key once they've passed (now or before); staff trying it always do.
        $reveal = (bool) (! $graded['recorded'] || $this->quizzes->summary($this->user($request), $quiz)['passed']);

        return ApiResponse::data($this->result($quiz, $graded['attempt'], $graded['recorded'], $reveal));
    }

    /**
     * Answers are revealed once the learner has passed this quiz at least once (and always
     * to the instructor and reviewers trying it).
     *
     * @return array{score_percent: int, correct_count: int, question_count: int, passed: bool, pass_percent: int, recorded: bool, answers_revealed: bool, submitted_at: string, review: list<array{question_id: int, chosen: list<int>, correct: list<int>, is_correct: bool, explanation: string|null}>}
     */
    private function result(Quiz $quiz, QuizAttempt $attempt, bool $recorded, bool $reveal): array
    {
        return [
            'score_percent' => $attempt->score_percent,
            'correct_count' => $attempt->correct_count,
            'question_count' => $attempt->question_count,
            'passed' => $attempt->passed,
            'pass_percent' => $quiz->pass_percent,
            'recorded' => $recorded,
            'answers_revealed' => $reveal,
            'submitted_at' => $attempt->submitted_at->toIso8601String(),
            /** @var list<array{question_id: int, chosen: list<int>, correct: list<int>, is_correct: bool, explanation: string|null}> */
            'review' => $this->quizzes->review($quiz, $attempt, $reveal),
        ];
    }

    /** @return array{lecture_id: int, pass_percent: int, shuffle_questions: bool, questions: list<array{id: int, type: 'single'|'multiple', prompt: string, explanation: string|null, options: list<array{id: int, body: string, is_correct: bool}>}>} */
    private function editor(Quiz $quiz): array
    {
        return [
            'lecture_id' => $quiz->lecture_id,
            'pass_percent' => $quiz->pass_percent,
            'shuffle_questions' => $quiz->shuffle_questions,
            /** @var list<array{id: int, type: 'single'|'multiple', prompt: string, explanation: string|null, options: list<array{id: int, body: string, is_correct: bool}>}> */
            'questions' => $quiz->questions->map(fn (QuizQuestion $q) => [
                'id' => $q->id,
                'type' => $q->type === QuizQuestion::MULTIPLE ? 'multiple' : 'single',
                'prompt' => $q->prompt,
                'explanation' => $q->explanation,
                'options' => $q->options->map(fn (QuizOption $o) => ['id' => $o->id, 'body' => $o->body, 'is_correct' => $o->is_correct])->values()->all(),
            ])->values()->all(),
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
