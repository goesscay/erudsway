<?php

namespace App\Modules\Engagement\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property int $user_id
 * @property string $title
 * @property string $body_html
 * @property int $recipients_count
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * @property-read Course $course
 * @property-read User $user
 */
class Announcement extends Model
{
    protected $fillable = ['course_id', 'user_id', 'title', 'body_html'];

    protected $attributes = ['recipients_count' => 0];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'recipients_count' => 'integer'];
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
}
