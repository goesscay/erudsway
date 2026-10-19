<?php

namespace App\Modules\Engagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $question_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_instructor
 * @property string $status visible | hidden
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Question $question
 */
class Answer extends Model
{
    protected $fillable = ['question_id', 'user_id', 'body', 'is_instructor'];

    protected $attributes = ['status' => 'visible', 'is_instructor' => false];

    protected function casts(): array
    {
        return ['is_instructor' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
