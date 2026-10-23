<?php

namespace App\Modules\Fulfilment\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property TaskType $type
 * @property TaskStatus $status
 * @property int $user_id
 * @property int $course_id
 * @property int|null $enrollment_id
 * @property int|null $edition_id
 * @property array<string, mixed>|null $data Simulator login, or the learner's doubt-session request
 * @property string|null $staff_notes
 * @property Carbon|null $scheduled_for
 * @property int $attempts
 * @property string|null $last_error
 * @property int|null $handled_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $access_ends_at When a simulator login's trial or plan ends
 * @property Carbon|null $reminded_at When the learner was told it's ending soon
 * @property Carbon|null $removed_at When the login was removed from the simulator
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Course $course
 * @property-read CourseEdition|null $edition
 * @property-read Enrollment|null $enrollment
 */
class FulfilmentTask extends Model
{
    use HasUuids;

    protected $fillable = ['type', 'status', 'user_id', 'course_id', 'enrollment_id', 'edition_id', 'data'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'status' => TaskStatus::class,
            'data' => 'encrypted:array',
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
            'access_ends_at' => 'datetime',
            'reminded_at' => 'datetime',
            'removed_at' => 'datetime',
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

    /** @return BelongsTo<CourseEdition, $this> */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'edition_id');
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
