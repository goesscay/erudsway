<?php

namespace App\Modules\Authoring\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One "submit for review" of a course and the moderator's decision.
 *
 * @property int $id
 * @property int $course_id
 * @property int $submitted_by
 * @property string $status pending|approved|rejected
 * @property int|null $reviewer_id
 * @property string|null $feedback
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property-read Course $course
 * @property-read User $submitter
 * @property-read User|null $reviewer
 */
class CourseSubmission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
