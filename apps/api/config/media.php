<?php

return [

    /*
    | Video backend. "local_hls" transcodes on this server with ffmpeg (decision D1).
    | A Cloudflare Stream provider can be added later behind the same interface.
    */
    'video_provider' => env('VIDEO_PROVIDER', 'local_hls'),

    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),

    /* Seconds a single transcode may run before it is killed. */
    'transcode_timeout' => (int) env('MEDIA_TRANSCODE_TIMEOUT', 3 * 3600),

    'uploads' => [
        'chunk_size' => 8 * 1024 * 1024,
        'max_bytes' => (int) env('MEDIA_MAX_UPLOAD_BYTES', 4 * 1024 * 1024 * 1024), // 4 GB
        'max_duration_seconds' => 4 * 3600,
        'session_ttl_hours' => 48,
        'video_mimes' => ['video/mp4', 'video/quicktime', 'video/x-matroska', 'video/webm', 'video/x-msvideo'],
    ],

    /* Renditions produced, smallest first; only those at or below the source height are kept. */
    'renditions' => [
        ['height' => 360, 'video_kbps' => 800],
        ['height' => 720, 'video_kbps' => 2800],
        ['height' => 1080, 'video_kbps' => 5000],
    ],

    'resources' => [
        'max_kb' => 50 * 1024,
        'mimes' => ['pdf', 'xlsx', 'xls', 'csv', 'docx', 'doc', 'pptx', 'zip', 'txt'],
    ],

    'thumbnails' => [
        'max_kb' => 5 * 1024,
        'width' => 1280,
        'height' => 720,
    ],
];
