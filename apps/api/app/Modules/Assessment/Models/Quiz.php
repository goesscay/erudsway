<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Catalog\Models\Lecture;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The questions behind a quiz lecture.
 *
 * @property int $id
 * @property int $lecture_id
 * @property int $pass_percent 0–100; 0 means any attempt passes
 * @property bool $shuffle_questions
 * @property-read Lecture $lecture
 * @property-read Collection<int, QuizQuestion> $questions
 */
class Quiz extends Model
{
    protected $fillable = ['lecture_id', 'pass_percent', 'shuffle_questions'];

    protected $attributes = ['pass_percent' => 70, 'shuffle_questions' => false];

    protected function casts(): array
    {
        return ['pass_percent' => 'integer', 'shuffle_questions' => 'boolean'];
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    /** @return HasMany<QuizQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }
}
