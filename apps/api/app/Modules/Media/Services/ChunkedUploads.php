<?php

namespace App\Modules\Media\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Media\Enums\VideoStatus;
use App\Modules\Media\Jobs\DeleteVideoAsset;
use App\Modules\Media\Jobs\ProcessVideo;
use App\Modules\Media\Models\UploadSession;
use App\Modules\Media\Models\VideoAsset;
use App\Modules\Media\Video\Ffmpeg;
use App\Modules\Media\Video\VideoProvider;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Resumable video uploads in fixed-size chunks, so large files survive flaky connections
 * and never need one giant request (Nginx keeps a small body limit).
 */
class ChunkedUploads
{
    public function __construct(
        private readonly Ffmpeg $ffmpeg,
        private readonly VideoProvider $provider,
        private readonly CourseStats $stats,
    ) {}

    public function start(User $user, Lecture $lecture, string $fileName, int $bytes, string $mime): UploadSession
    {
        $chunk = (int) config('media.uploads.chunk_size');

        return DB::transaction(function () use ($user, $lecture, $fileName, $bytes, $mime, $chunk) {
            $asset = VideoAsset::query()->create([
                'owner_id' => $user->id,
                'provider' => $this->provider->name(),
                'original_name' => mb_substr($fileName, 0, 255),
                'source_size_bytes' => $bytes,
            ]);

            return UploadSession::query()->create([
                'user_id' => $user->id,
                'video_asset_id' => $asset->id,
                'lecture_id' => $lecture->id,
                'mime' => $mime,
                'total_bytes' => $bytes,
                'chunk_size' => $chunk,
                'total_chunks' => (int) ceil($bytes / $chunk),
                'received_chunks' => [],
                'expires_at' => now()->addHours((int) config('media.uploads.session_ttl_hours')),
            ]);
        });
    }

    /**
     * Store one chunk from a stream. Re-sending a chunk overwrites it, so retries are safe.
     *
     * @param  resource  $stream
     */
    public function storeChunk(UploadSession $session, int $index, $stream): UploadSession
    {
        $this->assertOpen($session);
        if ($index < 0 || $index >= $session->total_chunks) {
            throw new DomainException('CHUNK_OUT_OF_RANGE', 'That chunk number is not part of this upload.', 422);
        }

        $disk = Storage::disk('local');
        $path = $session->partPath($index);
        $disk->makeDirectory(dirname($path));
        $target = fopen($disk->path($path), 'wb');
        if ($target === false) {
            throw new RuntimeException('Could not open upload storage.');
        }
        $written = stream_copy_to_stream($stream, $target, $session->expectedChunkBytes($index) + 1);
        fclose($target);

        if ($written !== $session->expectedChunkBytes($index)) {
            $disk->delete($path);
            throw new DomainException('CHUNK_SIZE_MISMATCH', "Chunk {$index} should be {$session->expectedChunkBytes($index)} bytes; received {$written}.", 422);
        }

        return DB::transaction(function () use ($session, $index) {
            /** @var UploadSession $locked */
            $locked = UploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $received = $locked->received_chunks;
            if (! in_array($index, $received, true)) {
                $received[] = $index;
                sort($received);
                $locked->forceFill(['received_chunks' => $received])->save();
            }

            return $locked;
        });
    }

    /** Assemble the chunks, check the file really is a video, attach it to the lecture and queue processing. */
    public function complete(UploadSession $session): VideoAsset
    {
        $this->assertOpen($session);
        if (! $session->isComplete()) {
            $missing = array_values(array_diff(range(0, $session->total_chunks - 1), $session->received_chunks));
            throw new DomainException('UPLOAD_INCOMPLETE', 'Some parts of the file have not arrived yet: '.implode(', ', array_slice($missing, 0, 10)), 422);
        }

        $disk = Storage::disk('local');
        $asset = $session->videoAsset;
        $ext = strtolower(pathinfo($asset->original_name, PATHINFO_EXTENSION)) ?: 'mp4';
        $sourcePath = "video-sources/{$asset->uuid}/source.".preg_replace('/[^a-z0-9]/', '', $ext);
        $disk->makeDirectory(dirname($sourcePath));

        $out = fopen($disk->path($sourcePath), 'wb');
        if ($out === false) {
            throw new RuntimeException('Could not open video storage.');
        }
        for ($i = 0; $i < $session->total_chunks; $i++) {
            $in = fopen($disk->path($session->partPath($i)), 'rb');
            if ($in === false) {
                throw new RuntimeException("Chunk {$i} is missing on disk.");
            }
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        $disk->deleteDirectory("uploads/{$session->uuid}");

        try {
            $this->ffmpeg->probe($disk->path($sourcePath));
        } catch (RuntimeException $e) {
            $disk->deleteDirectory(dirname($sourcePath));
            $asset->forceFill(['status' => VideoStatus::Failed, 'failure_reason' => $e->getMessage()])->save();
            $session->forceFill(['completed_at' => now()])->save();
            throw new DomainException('NOT_A_VIDEO', $e->getMessage(), 422);
        }

        $lecture = $session->lecture;
        $previous = $lecture->video_asset_id;

        DB::transaction(function () use ($asset, $session, $lecture, $sourcePath) {
            $asset->forceFill(['status' => VideoStatus::Processing, 'source_path' => $sourcePath])->save();
            $session->forceFill(['completed_at' => now()])->save();
            $lecture->forceFill(['video_asset_id' => $asset->id, 'duration_seconds' => 0])->save();
            $this->stats->refresh($lecture->course_id);
        });

        if ($previous && $previous !== $asset->id) {
            DeleteVideoAsset::dispatch($previous);
        }
        ProcessVideo::dispatch($asset);

        return $asset->refresh();
    }

    private function assertOpen(UploadSession $session): void
    {
        if ($session->completed_at !== null) {
            throw new DomainException('UPLOAD_CLOSED', 'This upload has already been completed.', 409);
        }
        if ($session->expires_at->isPast()) {
            throw new DomainException('UPLOAD_EXPIRED', 'This upload expired. Please start again.', 410);
        }
    }
}
