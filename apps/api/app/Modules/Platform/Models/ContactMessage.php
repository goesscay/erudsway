<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Platform\Enums\ContactTopic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property ContactTopic $topic
 * @property string $message
 * @property string $status 'new' | 'handled'
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read User|null $handler
 */
class ContactMessage extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'topic', 'message', 'ip'];

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
            'topic' => ContactTopic::class,
            'handled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
