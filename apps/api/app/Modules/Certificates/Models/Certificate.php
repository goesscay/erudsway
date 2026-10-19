<?php

namespace App\Modules\Certificates\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A certificate of completion. Its wording is fixed at issue; it stops being valid if the
 * learner's access to the course ends (a refund or an admin revoke).
 *
 * @property int $id
 * @property int $enrollment_id
 * @property int $user_id
 * @property int $course_id
 * @property string $serial Printed on the certificate, e.g. ERW-C-2026-00042
 * @property string $code Public verification code, e.g. K7Q2-M9XP-4TRA
 * @property string $recipient_name
 * @property string $course_title
 * @property string $instructor_name
 * @property int $duration_seconds
 * @property Carbon $issued_at
 * @property-read Enrollment $enrollment
 * @property-read Course $course
 * @property-read User $user
 */
class Certificate extends Model
{
    protected $fillable = ['enrollment_id', 'user_id', 'course_id', 'serial', 'code', 'recipient_name', 'course_title', 'instructor_name', 'duration_seconds', 'issued_at'];

    protected function casts(): array
    {
        return ['duration_seconds' => 'integer', 'issued_at' => 'datetime'];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->enrollment->status === EnrollmentStatus::Active;
    }

    public function verifyUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/certificates/'.$this->code;
    }
}
