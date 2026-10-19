<?php

namespace App\Modules\Learning\Models;

use App\Modules\Catalog\Models\Lecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's private note pinned to a moment in a lecture.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $lecture_id
 * @property int $at_seconds
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Note extends Model
{
    protected $fillable = ['user_id', 'course_id', 'lecture_id', 'at_seconds', 'body'];

    protected function casts(): array
    {
        return ['at_seconds' => 'integer'];
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }
}
