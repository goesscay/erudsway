<?php

namespace App\Modules\Engagement\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A learner's question in a course's Q&A, optionally about one lecture.
 *
 * @property int $id
 * @property int $course_id
 * @property int|null $lecture_id
 * @property int $user_id
 * @property string $title
 * @property string $body
 * @property string $status visible | hidden
 * @property int $answers_count
 * @property bool $instructor_answered
 * @property Carbon $last_activity_at
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Course $course
 * @property-read Lecture|null $lecture
 * @property-read Collection<int, Answer> $answers
 */
class Question extends Model
{
    protected $fillable = ['course_id', 'lecture_id', 'user_id', 'title', 'body', 'last_activity_at'];

    protected $attributes = ['status' => 'visible', 'answers_count' => 0, 'instructor_answered' => false];

    protected function casts(): array
    {
        return [
            'answers_count' => 'integer',
            'instructor_answered' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    /** @return HasMany<Answer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('id');
    }
}
