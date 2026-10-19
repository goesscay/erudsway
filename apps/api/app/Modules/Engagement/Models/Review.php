<?php

namespace App\Modules\Engagement\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Engagement\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $rating 1–5
 * @property string|null $body
 * @property ReviewStatus $status
 * @property int $report_count
 * @property string|null $instructor_reply
 * @property Carbon|null $replied_at
 * @property string|null $moderation_note
 * @property int|null $moderated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Course $course
 * @property-read Collection<int, ReviewReport> $reports
 */
class Review extends Model
{
    protected $fillable = ['user_id', 'course_id', 'rating', 'body'];

    /** Defaults mirrored from the schema, so a freshly saved review is complete. */
    protected $attributes = ['status' => 'visible', 'report_count' => 0];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'report_count' => 'integer',
            'replied_at' => 'datetime',
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

    /** @return HasMany<ReviewReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class)->latest();
    }

    /**
     * Reviews shown on the course page and counted in its rating.
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('status', '!=', ReviewStatus::Hidden->value);
    }
}
