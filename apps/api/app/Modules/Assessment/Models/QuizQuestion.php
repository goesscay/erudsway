<?php

namespace App\Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $quiz_id
 * @property string $type single | multiple
 * @property string $prompt
 * @property string|null $explanation Shown after an attempt
 * @property int $position
 * @property-read Collection<int, QuizOption> $options
 */
class QuizQuestion extends Model
{
    public const SINGLE = 'single';

    public const MULTIPLE = 'multiple';

    protected $fillable = ['quiz_id', 'type', 'prompt', 'explanation', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** @return HasMany<QuizOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class, 'question_id')->orderBy('position');
    }

    /** @return list<int> */
    public function correctIds(): array
    {
        return $this->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }
}
