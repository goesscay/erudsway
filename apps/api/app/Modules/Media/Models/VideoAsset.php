<?php

namespace App\Modules\Media\Models;

use App\Models\User;
use App\Modules\Media\Enums\VideoStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $owner_id
 * @property string $provider
 * @property string|null $provider_uid
 * @property VideoStatus $status
 * @property string $original_name
 * @property string|null $source_path
 * @property int $source_size_bytes
 * @property string|null $hls_path
 * @property string|null $encryption_key
 * @property list<array{height: int, bandwidth: int, playlist: string}>|null $renditions
 * @property int $duration_seconds
 * @property string|null $poster_path
 * @property string|null $failure_reason
 * @property Carbon|null $processed_at
 */
class VideoAsset extends Model
{
    use HasUuids;

    protected $fillable = ['owner_id', 'provider', 'original_name', 'source_size_bytes'];

    protected $hidden = ['encryption_key'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'uploading'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'status' => VideoStatus::class,
            'encryption_key' => 'encrypted',
            'renditions' => 'array',
            'duration_seconds' => 'integer',
            'source_size_bytes' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isReady(): bool
    {
        return $this->status === VideoStatus::Ready;
    }
}
