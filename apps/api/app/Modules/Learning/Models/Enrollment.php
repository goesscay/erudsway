<?php

namespace App\Modules\Learning\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Learning\Enums\EnrollmentSource;
use App\Modules\Learning\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A learner's access to a course. Access exists only while `status` is active.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property EnrollmentSource $source
 * @property EnrollmentStatus $status
 * @property int|null $order_item_id
 * @property int|null $granted_by
 * @property string|null $reason
 * @property int|null $edition_id Null: the whole course (single-price courses, grants)
 * @property Carbon $granted_at
 * @property Carbon|null $expires_at Null: no end date
 * @property-read CourseEdition|null $edition
 * @property Carbon|null $revoked_at
 * @property int $completed_count
 * @property int $progress_percent
 * @property int $peak_progress_percent Highest progress_percent ever reached
 * @property int|null $last_lecture_id
 * @property Carbon|null $last_accessed_at
 * @property Carbon|null $completed_at
 * @property-read Certificate|null $certificate
 * @property Carbon $created_at
 */
class Enrollment extends Model
{
    protected $fillable = ['user_id', 'course_id', 'source', 'status', 'granted_by', 'reason', 'granted_at'];

    protected function casts(): array
    {
        return [
            'source' => EnrollmentSource::class,
            'status' => EnrollmentStatus::class,
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'completed_at' => 'datetime',
            'completed_count' => 'integer',
            'progress_percent' => 'integer',
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

    /** @return BelongsTo<User, $this> */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lastLecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class, 'last_lecture_id');
    }

    /** Active and not past its access period. */
    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function hasExpired(): bool
    {
        return $this->status === EnrollmentStatus::Active && $this->expires_at !== null && ! $this->expires_at->isFuture();
    }

    /** @return BelongsTo<CourseEdition, $this> */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'edition_id');
    }

    /**
     * @param  Builder<Enrollment>  $query
     * @return Builder<Enrollment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
