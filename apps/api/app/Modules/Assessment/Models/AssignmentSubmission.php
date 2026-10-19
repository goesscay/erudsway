<?php

namespace App\Modules\Assessment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assignment_id
 * @property int $user_id
 * @property string|null $body
 * @property string|null $file_path On the private disk
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string $status submitted | reviewed
 * @property string|null $feedback
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon $submitted_at
 * @property-read Assignment $assignment
 * @property-read User $user
 */
class AssignmentSubmission extends Model
{
    public const SUBMITTED = 'submitted';

    public const REVIEWED = 'reviewed';

    protected $fillable = ['assignment_id', 'user_id', 'body', 'file_path', 'file_name', 'file_size', 'submitted_at'];

    protected $attributes = ['status' => self::SUBMITTED];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'reviewed_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
