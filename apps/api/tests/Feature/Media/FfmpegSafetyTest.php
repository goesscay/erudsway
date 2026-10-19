<?php

use App\Modules\Media\Video\Ffmpeg;

it('refuses playlists dressed up as videos, so ffmpeg never follows them to other files', function () {
    $ffmpeg = app(Ffmpeg::class);
    if (! $ffmpeg->available()) {
        $this->markTestSkipped('ffmpeg is not installed');
    }

    $dir = sys_get_temp_dir().'/erudsway-ffmpeg-'.bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents("{$dir}/secret.txt", 'APP_KEY=base64:do-not-leak');
    // An HLS playlist pointing at a local file, uploaded with a video extension.
    file_put_contents("{$dir}/lecture.mp4", "#EXTM3U\n#EXT-X-TARGETDURATION:10\n#EXTINF:10,\nfile://{$dir}/secret.txt\n#EXT-X-ENDLIST\n");
    file_put_contents("{$dir}/list.mp4", "ffconcat version 1.0\nfile '{$dir}/secret.txt'\n");

    expect(fn () => $ffmpeg->probe("{$dir}/lecture.mp4"))->toThrow(RuntimeException::class)
        ->and(fn () => $ffmpeg->probe("{$dir}/list.mp4"))->toThrow(RuntimeException::class);

    array_map('unlink', glob("{$dir}/*") ?: []);
    rmdir($dir);
});

it('forces the probed container and local-only input when transcoding', function () {
    expect(Ffmpeg::input('/x/in.mp4', 'mov'))->toBe(['-protocol_whitelist', 'file', '-f', 'mov', '-i', '/x/in.mp4']);
});
