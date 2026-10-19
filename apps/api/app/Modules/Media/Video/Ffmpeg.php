<?php

namespace App\Modules\Media\Video;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Thin wrapper around the ffmpeg/ffprobe binaries. */
class Ffmpeg
{
    /**
     * Containers we accept, keyed by a word in ffprobe's format_name, mapped to the demuxer
     * we then force with -f. Anything else — notably HLS/concat playlists renamed to .mp4,
     * which make ffmpeg read other files — is refused.
     */
    private const CONTAINERS = ['mov' => 'mov', 'matroska' => 'matroska', 'avi' => 'avi', 'mpegts' => 'mpegts'];

    /**
     * @return array{duration: float, width: int, height: int, has_audio: bool, format: string}
     */
    public function probe(string $path): array
    {
        $process = new Process([
            config('media.ffprobe'), '-v', 'error', '-protocol_whitelist', 'file',
            '-print_format', 'json', '-show_format', '-show_streams', $path,
        ]);
        $process->setTimeout(120)->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('This file could not be read as a video.');
        }

        /** @var array{format?: array{duration?: string, format_name?: string}, streams?: list<array{codec_type?: string, width?: int, height?: int}>} $info */
        $info = json_decode($process->getOutput(), true) ?: [];
        $names = explode(',', (string) ($info['format']['format_name'] ?? ''));
        $format = collect(self::CONTAINERS)->first(fn (string $demuxer, string $name) => in_array($name, $names, true));
        if ($format === null) {
            throw new RuntimeException('Upload an MP4, MOV, MKV, WebM or AVI video.');
        }
        $video = collect($info['streams'] ?? [])->firstWhere('codec_type', 'video');
        if (! $video) {
            throw new RuntimeException('The file does not contain a video stream.');
        }

        return [
            'duration' => (float) ($info['format']['duration'] ?? 0),
            'width' => (int) ($video['width'] ?? 0),
            'height' => (int) ($video['height'] ?? 0),
            'has_audio' => collect($info['streams'] ?? [])->contains('codec_type', 'audio'),
            'format' => $format,
        ];
    }

    /**
     * Run ffmpeg with the given arguments. On Linux the process runs at low CPU priority so
     * transcoding never starves the website.
     *
     * @param  list<string>  $args
     */
    public function run(array $args, int $timeout): void
    {
        $command = [config('media.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-y', ...$args];
        if (PHP_OS_FAMILY !== 'Windows') {
            $command = ['nice', '-n', '15', ...$command];
        }

        $process = new Process($command);
        $process->setTimeout($timeout)->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('ffmpeg failed: '.mb_substr(trim($process->getErrorOutput()), -1500));
        }
    }

    public function available(): bool
    {
        $process = new Process([config('media.ffmpeg'), '-version']);
        $process->setTimeout(10)->run();

        return $process->isSuccessful();
    }

    /**
     * Input arguments for an uploaded file: read only from local files, in the container
     * ffprobe identified — never auto-detected.
     *
     * @return list<string>
     */
    public static function input(string $path, string $format): array
    {
        return ['-protocol_whitelist', 'file', '-f', $format, '-i', $path];
    }
}
