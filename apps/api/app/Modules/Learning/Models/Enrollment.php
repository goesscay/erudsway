<?php

namespace App\Modules\Learning\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
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
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 * @property int $completed_count
 * @property int $progress_percent
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

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }

    /**
     * @param  Builder<Enrollment>  $query
     * @return Builder<Enrollment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EnrollmentStatus::Active->value);
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
