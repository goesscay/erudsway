<?php

namespace App\Modules\Assessment\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $question_id
 * @property string $body
 * @property bool $is_correct
 * @property int $position
 */
class QuizOption extends Model
{
    protected $fillable = ['question_id', 'body', 'is_correct', 'position'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean', 'position' => 'integer'];
    }
}
