<?php

namespace App\Modules\Media\Video;

use App\Modules\Media\Enums\VideoStatus;
use App\Modules\Media\Models\VideoAsset;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Transcodes on this server into AES-128 encrypted HLS (decision D1).
 *
 * Layout on the private disk:
 *   video-sources/{uuid}/source.ext   original upload (kept for re-encoding or a later move to Stream)
 *   video-hls/{uuid}/master.m3u8      variant playlist
 *   video-hls/{uuid}/{h}p.m3u8, {h}p_0001.ts …   renditions
 *   video-hls/{uuid}/poster.jpg
 *
 * The key never touches a served directory: it is stored encrypted on the asset and handed
 * only to entitled learners by the playback API (Phase 4), which also rewrites the key URI.
 */
class LocalHlsProvider implements VideoProvider
{
    public const KEY_URI_PLACEHOLDER = 'erudsway-key';

    public function __construct(private readonly Ffmpeg $ffmpeg) {}

    public function name(): string
    {
        return 'local_hls';
    }

    public function process(VideoAsset $asset): void
    {
        $disk = $this->disk();
        if ($asset->source_path === null || ! $disk->exists($asset->source_path)) {
            throw new RuntimeException('The uploaded file is missing.');
        }

        $source = $disk->path($asset->source_path);
        $probe = $this->ffmpeg->probe($source);
        if ($probe['duration'] < 1) {
            throw new RuntimeException('The video is too short.');
        }
        if ($probe['duration'] > config('media.uploads.max_duration_seconds')) {
            throw new RuntimeException('Videos can be at most 4 hours long.');
        }

        $dir = "video-hls/{$asset->uuid}";
        $disk->deleteDirectory($dir);
        $disk->makeDirectory($dir);
        // The private disk creates owner-only folders; Nginx (the folder's group, via the
        // setgid parent) must be able to enter it to serve segments and the poster.
        @chmod($disk->path($dir), 0750);
        $out = $disk->path($dir);

        $key = random_bytes(16);
        $keyFile = $disk->path("tmp/{$asset->uuid}.key");
        $keyInfo = $disk->path("tmp/{$asset->uuid}.keyinfo");
        $disk->makeDirectory('tmp');
        file_put_contents($keyFile, $key);
        file_put_contents($keyInfo, self::KEY_URI_PLACEHOLDER."\n{$keyFile}\n".bin2hex(random_bytes(16))."\n");

        try {
            $renditions = [];
            foreach ($this->renditionsFor($probe['height']) as $r) {
                $h = $r['height'];
                $w = (int) (round($probe['width'] * $h / max(1, $probe['height']) / 2) * 2);
                $this->ffmpeg->run([
                    ...Ffmpeg::input($source, $probe['format']),
                    '-map', '0:v:0',
                    ...($probe['has_audio'] ? ['-map', '0:a:0', '-c:a', 'aac', '-b:a', '128k', '-ac', '2'] : []),
                    '-vf', "scale=-2:{$h}",
                    '-c:v', 'libx264', '-preset', 'veryfast', '-profile:v', 'main', '-pix_fmt', 'yuv420p',
                    '-crf', '21', '-maxrate', "{$r['video_kbps']}k", '-bufsize', ($r['video_kbps'] * 2).'k',
                    '-g', '48', '-keyint_min', '48', '-sc_threshold', '0',
                    '-f', 'hls', '-hls_time', '6', '-hls_playlist_type', 'vod',
                    '-hls_key_info_file', $keyInfo,
                    '-hls_segment_filename', "{$out}/{$h}p_%04d.ts",
                    "{$out}/{$h}p.m3u8",
                ], (int) config('media.transcode_timeout'));

                $renditions[] = [
                    'height' => $h,
                    'width' => $w,
                    'bandwidth' => (int) (($r['video_kbps'] + ($probe['has_audio'] ? 128 : 0)) * 1100),
                    'playlist' => "{$h}p.m3u8",
                ];
            }

            $disk->put("{$dir}/master.m3u8", $this->masterPlaylist($renditions));

            $this->ffmpeg->run([
                '-ss', (string) min(3, $probe['duration'] / 2), ...Ffmpeg::input($source, $probe['format']),
                '-frames:v', '1', '-vf', 'scale=1280:-2', '-q:v', '3', "{$out}/poster.jpg",
            ], 120);
        } catch (Throwable $e) {
            $disk->deleteDirectory($dir);
            throw $e;
        } finally {
            @unlink($keyFile);
            @unlink($keyInfo);
        }

        $asset->forceFill([
            'status' => VideoStatus::Ready,
            'hls_path' => $dir,
            'encryption_key' => base64_encode($key),
            'renditions' => $renditions,
            'duration_seconds' => (int) round($probe['duration']),
            'poster_path' => "{$dir}/poster.jpg",
            'failure_reason' => null,
            'processed_at' => now(),
        ])->save();
    }

    public function delete(VideoAsset $asset): void
    {
        $this->disk()->deleteDirectory("video-hls/{$asset->uuid}");
        $this->disk()->deleteDirectory("video-sources/{$asset->uuid}");
    }

    /**
     * Configured renditions at or below the source height; a low-resolution source still
     * gets one rendition at its own (even) height.
     *
     * @return list<array{height: int, video_kbps: int}>
     */
    private function renditionsFor(int $sourceHeight): array
    {
        /** @var list<array{height: int, video_kbps: int}> $all */
        $all = config('media.renditions');
        $fit = array_values(array_filter($all, fn (array $r) => $r['height'] <= $sourceHeight));

        return $fit ?: [['height' => max(2, intdiv($sourceHeight, 2) * 2), 'video_kbps' => $all[0]['video_kbps']]];
    }

    /** @param list<array{height: int, width: int, bandwidth: int, playlist: string}> $renditions */
    private function masterPlaylist(array $renditions): string
    {
        $lines = ['#EXTM3U', '#EXT-X-VERSION:3'];
        foreach ($renditions as $r) {
            $lines[] = "#EXT-X-STREAM-INF:BANDWIDTH={$r['bandwidth']},RESOLUTION={$r['width']}x{$r['height']}";
            $lines[] = $r['playlist'];
        }

        return implode("\n", $lines)."\n";
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
