<?php

namespace App\Modules\Media\Models;

use App\Modules\Catalog\Models\Lecture;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Resumable chunked upload of one video file.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $video_asset_id
 * @property int $lecture_id
 * @property string $mime
 * @property int $total_bytes
 * @property int $chunk_size
 * @property int $total_chunks
 * @property list<int> $received_chunks
 * @property Carbon|null $completed_at
 * @property Carbon $expires_at
 * @property-read VideoAsset $videoAsset
 * @property-read Lecture $lecture
 */
class UploadSession extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

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
            'received_chunks' => 'array',
            'total_bytes' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<VideoAsset, $this> */
    public function videoAsset(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class);
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    /** Expected byte length of chunk $index (the last one may be shorter). */
    public function expectedChunkBytes(int $index): int
    {
        return $index === $this->total_chunks - 1
            ? $this->total_bytes - $this->chunk_size * ($this->total_chunks - 1)
            : $this->chunk_size;
    }

    public function partPath(int $index): string
    {
        return "uploads/{$this->uuid}/".str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part';
    }

    public function isComplete(): bool
    {
        return count($this->received_chunks) === $this->total_chunks;
    }
}
