<?php

namespace App\Modules\Engagement\Http\Resources;

use App\Modules\Engagement\Models\Answer;
use App\Modules\Engagement\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A Q&A thread. `answers` is included only when the thread is opened.
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'author' => ['name' => $this->user->name],
            'is_mine' => $request->user()?->getAuthIdentifier() === $this->user_id,
            'lecture' => $this->lecture ? ['id' => $this->lecture->id, 'title' => $this->lecture->title] : null,
            /** @var 'visible'|'hidden' */
            'status' => $this->status,
            'answers_count' => $this->answers_count,
            'instructor_answered' => $this->instructor_answered,
            'course' => ['slug' => $this->course->slug, 'title' => $this->course->title],
            'created_at' => $this->created_at->toIso8601String(),
            'last_activity_at' => $this->last_activity_at->toIso8601String(),
            /** @var list<array{id: int, body: string, author: array{name: string}, is_instructor: bool, status: 'visible'|'hidden', created_at: string}> */
            'answers' => $this->whenLoaded('answers', fn () => $this->answers->map(fn (Answer $a) => [
                'id' => $a->id,
                'body' => $a->body,
                'author' => ['name' => $a->user->name],
                'is_instructor' => $a->is_instructor,
                'status' => $a->status,
                'created_at' => $a->created_at->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
