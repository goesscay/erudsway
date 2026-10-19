<?php

namespace App\Modules\Learning\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Where a learner is in one lecture. Composite key (user_id, lecture_id), so rows are
 * written with query-builder upserts rather than save().
 *
 * @property int $user_id
 * @property int $lecture_id
 * @property int $course_id
 * @property int $last_position_seconds
 * @property int $max_position_seconds
 * @property Carbon|null $completed_at
 */
class LectureProgress extends Model
{
    protected $table = 'lecture_progress';

    public $incrementing = false;

    protected $primaryKey = 'lecture_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_position_seconds' => 'integer',
            'max_position_seconds' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
