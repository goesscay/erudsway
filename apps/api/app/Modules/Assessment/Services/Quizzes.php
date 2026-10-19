<?php

namespace App\Modules\Assessment\Services;

use App\Models\User;
use App\Modules\Assessment\Models\Quiz;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Learning\Services\ProgressTracker;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Building and taking quizzes. A quiz is graded the moment it's submitted; a question is
 * right only when exactly the correct options are chosen. Passing completes the lecture,
 * and a later lower score never un-completes it.
 */
class Quizzes
{
    /** Each question adds a minute to the course's listed length. */
    public const SECONDS_PER_QUESTION = 60;

    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly ProgressTracker $progress,
        private readonly CourseStats $stats,
    ) {}

    public function for(Lecture $lecture): Quiz
    {
        if ($lecture->kind !== LectureKind::Quiz) {
            throw new DomainException('NOT_A_QUIZ', 'This lecture isn’t a quiz.', 422);
        }

        return Quiz::query()->firstOrCreate(['lecture_id' => $lecture->id])->load('questions.options');
    }

    /**
     * Replace the quiz's questions with the given list. Questions and options that carry an
     * id are updated in place (so earlier attempts still line up); the rest are created, and
     * anything left out is deleted.
     *
     * @param  array{pass_percent: int, shuffle_questions?: bool, questions: list<array{id?: int|null, type: string, prompt: string, explanation?: string|null, options: list<array{id?: int|null, body: string, is_correct: bool}>}>}  $data
     */
    public function save(Lecture $lecture, array $data): Quiz
    {
        $quiz = $this->for($lecture);

        foreach ($data['questions'] as $i => $q) {
            $correct = count(array_filter($q['options'], fn ($o) => $o['is_correct']));
            if ($q['type'] === QuizQuestion::SINGLE && $correct !== 1) {
                throw new DomainException('INVALID_QUIZ', 'Question '.($i + 1).' needs exactly one correct answer.', 422);
            }
            if ($correct === 0) {
                throw new DomainException('INVALID_QUIZ', 'Question '.($i + 1).' needs at least one correct answer.', 422);
            }
        }

        DB::transaction(function () use ($quiz, $data, $lecture) {
            $quiz->forceFill(['pass_percent' => $data['pass_percent'], 'shuffle_questions' => (bool) ($data['shuffle_questions'] ?? false)])->save();

            $existing = $quiz->questions->keyBy('id');
            $keep = [];
            foreach ($data['questions'] as $i => $q) {
                $question = isset($q['id']) ? $existing->get($q['id']) : null;
                $question ??= new QuizQuestion(['quiz_id' => $quiz->id]);
                $question->fill([
                    'type' => $q['type'],
                    'prompt' => trim($q['prompt']),
                    'explanation' => isset($q['explanation']) && trim($q['explanation']) !== '' ? trim($q['explanation']) : null,
                    'position' => $i + 1,
                ])->save();
                $keep[] = $question->id;

                $options = $question->exists ? $question->options()->get()->keyBy('id') : collect();
                $keepOptions = [];
                foreach ($q['options'] as $j => $o) {
                    $option = isset($o['id']) ? $options->get($o['id']) : null;
                    $option ??= $question->options()->make();
                    $option->fill(['body' => trim($o['body']), 'is_correct' => (bool) $o['is_correct'], 'position' => $j + 1])->save();
                    $keepOptions[] = $option->id;
                }
                $question->options()->whereNotIn('id', $keepOptions)->delete();
            }
            $quiz->questions()->whereNotIn('id', $keep)->delete();

            $lecture->forceFill(['duration_seconds' => max(self::SECONDS_PER_QUESTION, count($keep) * self::SECONDS_PER_QUESTION)])->save();
        });
        $this->stats->refresh($lecture->course_id);

        return $quiz->fresh(['questions.options']) ?? $quiz;
    }

    /**
     * Grade an attempt. Enrolled learners' attempts are recorded; the instructor and
     * reviewers can try the quiz, but nothing is saved for them.
     *
     * @param  array<int, list<int>>  $answers  question id => chosen option ids
     * @return array{attempt: QuizAttempt, recorded: bool, review: list<array{question_id: int, chosen: list<int>, correct: list<int>, is_correct: bool, explanation: string|null}>}
     */
    public function submit(User $user, Lecture $lecture, array $answers): array
    {
        $role = $this->entitlements->role($user, $lecture->course);
        if ($role === null) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to take its quizzes.', 403);
        }
        $quiz = $this->for($lecture);
        if ($quiz->questions->isEmpty()) {
            throw new DomainException('QUIZ_EMPTY', 'This quiz has no questions yet.', 422);
        }

        [$chosen, $correctCount] = $this->grade($quiz, $answers);
        $total = $quiz->questions->count();
        $score = intdiv($correctCount * 100, $total);

        $attempt = new QuizAttempt([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'answers' => $chosen,
            'correct_count' => $correctCount,
            'question_count' => $total,
            'score_percent' => $score,
            'passed' => $score >= $quiz->pass_percent,
            'submitted_at' => now(),
        ]);

        $recorded = $role === Entitlements::ROLE_LEARNER;
        if ($recorded) {
            DB::transaction(function () use ($attempt, $user, $lecture) {
                $attempt->save();
                if ($attempt->passed) {
                    $this->progress->setCompleted($user, $lecture, true);
                }
            });
        }

        return ['attempt' => $attempt, 'recorded' => $recorded, 'review' => $this->review($quiz, $attempt, $attempt->passed)];
    }

    /** @return array{count: int, best_percent: int|null, passed: bool, last: QuizAttempt|null} */
    public function summary(User $user, Quiz $quiz): array
    {
        $attempts = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('user_id', $user->id);

        return [
            'count' => (clone $attempts)->count(),
            'best_percent' => ($best = (clone $attempts)->max('score_percent')) !== null ? (int) $best : null,
            'passed' => (clone $attempts)->where('passed', true)->exists(),
            'last' => (clone $attempts)->latest('submitted_at')->latest('id')->first(),
        ];
    }

    /**
     * Per-question result for an attempt, against the quiz as it is now. Questions added
     * since the attempt show as unanswered. The right answers and explanations are only
     * revealed once the learner has passed — otherwise a failed attempt would hand them
     * the answer key and the pass mark would mean nothing.
     *
     * @return list<array{question_id: int, chosen: list<int>, correct: list<int>, is_correct: bool, explanation: string|null}>
     */
    public function review(Quiz $quiz, QuizAttempt $attempt, bool $reveal): array
    {
        return $quiz->questions->map(function (QuizQuestion $q) use ($attempt, $reveal) {
            $chosen = array_map('intval', $attempt->answers[$q->id] ?? []);
            sort($chosen);

            return [
                'question_id' => $q->id,
                'chosen' => $chosen,
                'correct' => $reveal ? $q->correctIds() : [],
                'is_correct' => $chosen === $q->correctIds(),
                'explanation' => $reveal ? $q->explanation : null,
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, list<int>>  $answers
     * @return array{0: array<int, list<int>>, 1: int}
     */
    private function grade(Quiz $quiz, array $answers): array
    {
        $chosen = [];
        $correct = 0;
        foreach ($quiz->questions as $q) {
            $valid = $q->options->pluck('id')->map(fn ($id) => (int) $id)->all();
            $picked = array_values(array_unique(array_map('intval', $answers[$q->id] ?? [])));
            if (array_diff($picked, $valid) !== []) {
                throw new DomainException('INVALID_ANSWER', 'An answer doesn’t belong to its question. Reload the quiz and try again.', 422);
            }
            if ($q->type === QuizQuestion::SINGLE && count($picked) > 1) {
                throw new DomainException('INVALID_ANSWER', 'Pick one answer for each single-choice question.', 422);
            }
            sort($picked);
            $chosen[$q->id] = $picked;
            if ($picked === $q->correctIds()) {
                $correct++;
            }
        }

        return [$chosen, $correct];
    }
}
