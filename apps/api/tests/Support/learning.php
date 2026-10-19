<?php

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Learning\Enums\EnrollmentSource;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Media\Models\VideoAsset;
use Illuminate\Support\Facades\Storage;

/*
| Shared helpers for learning tests (loaded from tests/Pest.php). Like the authoring
| helpers, they need the "registration-returns" subcategory to exist.
*/

/** A published course with five 7-minute video lectures. */
function liveCourse(bool $free = true): Course
{
    $course = readyCourse();
    $course->forceFill([
        'status' => 'published',
        'published_at' => now(),
        'price_tier_id' => $free ? null : PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => 59900])->id,
        'lecture_count' => 5,
        'duration_seconds' => 2100,
    ])->save();

    return $course->fresh();
}

function enrollIn(Course $course, ?User $user = null): Enrollment
{
    $user ??= makeUser();

    return Enrollment::query()->create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'source' => EnrollmentSource::Free,
        'granted_at' => now(),
    ]);
}

/** Writes a small encrypted-HLS layout for the asset to the (faked) local disk. */
function fakeHls(VideoAsset $asset): VideoAsset
{
    Storage::fake('local');
    $dir = "video-hls/{$asset->uuid}";
    Storage::disk('local')->put("{$dir}/master.m3u8", "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-STREAM-INF:BANDWIDTH=1000000,RESOLUTION=640x360\n360p.m3u8\n#EXT-X-STREAM-INF:BANDWIDTH=3000000,RESOLUTION=1280x720\n720p.m3u8\n");
    foreach ([360, 720] as $h) {
        Storage::disk('local')->put("{$dir}/{$h}p.m3u8", implode("\n", [
            '#EXTM3U', '#EXT-X-VERSION:3', '#EXT-X-TARGETDURATION:6', '#EXT-X-PLAYLIST-TYPE:VOD',
            '#EXT-X-KEY:METHOD=AES-128,URI="erudsway-key",IV=0x00000000000000000000000000000001',
            '#EXTINF:6.000000,', "{$h}p_0000.ts", '#EXTINF:4.000000,', "{$h}p_0001.ts", '#EXT-X-ENDLIST', '',
        ]));
        Storage::disk('local')->put("{$dir}/{$h}p_0000.ts", 'segment-bytes');
    }
    Storage::disk('local')->put("{$dir}/poster.jpg", 'jpeg');

    $asset->forceFill([
        'hls_path' => $dir,
        'poster_path' => "{$dir}/poster.jpg",
        'renditions' => [['height' => 360, 'width' => 640, 'bandwidth' => 1000000, 'playlist' => '360p.m3u8'], ['height' => 720, 'width' => 1280, 'bandwidth' => 3000000, 'playlist' => '720p.m3u8']],
        'encryption_key' => base64_encode('0123456789abcdef'),
    ])->save();

    return $asset;
}
