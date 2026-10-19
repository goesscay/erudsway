<?php

namespace App\Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_id
 * @property int $user_id
 * @property array<int, list<int>> $answers question id => chosen option ids
 * @property int $correct_count
 * @property int $question_count
 * @property int $score_percent
 * @property bool $passed
 * @property Carbon $submitted_at
 * @property-read Quiz $quiz
 */
class QuizAttempt extends Model
{
    protected $fillable = ['quiz_id', 'user_id', 'answers', 'correct_count', 'question_count', 'score_percent', 'passed', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'correct_count' => 'integer',
            'question_count' => 'integer',
            'score_percent' => 'integer',
            'passed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
