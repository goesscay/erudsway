<?php

namespace App\Modules\Engagement\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Engagement\Models\Answer;
use App\Modules\Engagement\Models\Question;
use App\Modules\Engagement\Notifications\NewAnswer;
use App\Modules\Engagement\Notifications\NewQuestion;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Course Q&A. Enrolled learners ask and answer; the instructor's answers are marked as such.
 * The instructor and course moderators can hide posts.
 */
class QuestionsAndAnswers
{
    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    public function ask(User $user, Course $course, ?Lecture $lecture, string $title, string $body): Question
    {
        if ($this->entitlements->role($user, $course) !== Entitlements::ROLE_LEARNER) {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to ask questions.', 403);
        }
        if ($lecture !== null && $lecture->course_id !== $course->id) {
            throw new DomainException('NOT_FOUND', 'Lecture not found.', 404);
        }

        $question = Question::query()->create([
            'course_id' => $course->id,
            'lecture_id' => $lecture?->id,
            'user_id' => $user->id,
            'title' => trim($title),
            'body' => trim($body),
            'last_activity_at' => now(),
        ]);
        $course->instructor->notify(new NewQuestion($question));

        return $question;
    }

    public function answer(User $user, Question $question, string $body): Answer
    {
        $role = $this->entitlements->role($user, $question->course);
        if ($role === null || $question->status === 'hidden') {
            throw new DomainException('NOT_ENROLLED', 'Enroll in this course to answer questions.', 403);
        }

        $answer = DB::transaction(function () use ($user, $question, $body, $role) {
            $isInstructor = $role === Entitlements::ROLE_INSTRUCTOR;
            $answer = Answer::query()->create(['question_id' => $question->id, 'user_id' => $user->id, 'body' => trim($body), 'is_instructor' => $isInstructor]);
            $question->forceFill([
                'answers_count' => $question->answers()->where('status', 'visible')->count(),
                'instructor_answered' => $question->instructor_answered || $isInstructor,
                'last_activity_at' => now(),
            ])->save();

            return $answer;
        });

        if ($question->user_id !== $user->id) {
            $question->user->notify(new NewAnswer($answer));
        }

        return $answer;
    }

    /** Hide (or show again) a question or answer. Instructor of the course, or a course moderator. */
    public function setHidden(User $user, Question|Answer $post, bool $hidden): void
    {
        $question = $post instanceof Answer ? $post->question : $post;
        $role = $this->entitlements->role($user, $question->course);
        if (! in_array($role, [Entitlements::ROLE_INSTRUCTOR, Entitlements::ROLE_REVIEWER], true)) {
            throw new DomainException('FORBIDDEN', 'Only the instructor or our moderators can do this.', 403);
        }

        $post->forceFill(['status' => $hidden ? 'hidden' : 'visible'])->save();
        if ($post instanceof Answer) {
            $question->forceFill(['answers_count' => $question->answers()->where('status', 'visible')->count()])->save();
        }
        $this->audit->record($hidden ? 'qa.hidden' : 'qa.restored', $post, null, null, $user);
    }
}
