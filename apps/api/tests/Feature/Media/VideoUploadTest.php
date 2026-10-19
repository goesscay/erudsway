<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Catalog\Services\CourseStats;
use App\Modules\Media\Enums\VideoStatus;
use App\Modules\Media\Jobs\ProcessVideo;
use App\Modules\Media\Models\VideoAsset;
use App\Modules\Media\Video\Ffmpeg;
use App\Modules\Media\Video\VideoProvider;
use Database\Factories\CourseFactory;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    $p = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $p->id]);
});

function videoLecture(): Lecture
{
    $course = draftFor();

    return $course->sections()->first()->lectures()->create(['course_id' => $course->id, 'title' => 'Intro', 'kind' => 'video', 'position' => 1]);
}

/** A real 3-second test video generated with ffmpeg, or null when ffmpeg isn't installed. */
function sampleVideo(): ?string
{
    if (! app(Ffmpeg::class)->available()) {
        return null;
    }
    $path = sys_get_temp_dir().'/erudsway-test-'.uniqid().'.mp4';
    (new Process([config('media.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-y',
        '-f', 'lavfi', '-i', 'testsrc=duration=3:size=640x360:rate=24',
        '-f', 'lavfi', '-i', 'sine=frequency=440:duration=3',
        '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-shortest', $path]))->mustRun();

    return $path;
}

function sendChunks($test, Lecture $lecture, string $bytes, string $name = 'intro.mp4', int $chunkSize = 64 * 1024): string
{
    config(['media.uploads.chunk_size' => $chunkSize]);
    $session = $test->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/lectures/{$lecture->id}/video-uploads", [
            'file_name' => $name, 'file_size' => strlen($bytes), 'mime' => 'video/mp4',
        ])->assertCreated()->json('data');

    // Send out of order to prove ordering doesn't matter.
    foreach (array_reverse(range(0, $session['total_chunks'] - 1)) as $i) {
        $test->actingAs($lecture->course->instructor)
            ->call('PUT', "/api/v1/instructor/uploads/{$session['id']}/chunks/{$i}", [], [], [], [
                'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json',
            ], substr($bytes, $i * $chunkSize, $chunkSize))
            ->assertOk();
    }

    return $session['id'];
}

it('starts an upload session sized in chunks', function () {
    $lecture = videoLecture();
    config(['media.uploads.chunk_size' => 8 * 1024 * 1024]);

    $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/lectures/{$lecture->id}/video-uploads", ['file_name' => 'a.mp4', 'file_size' => 20 * 1024 * 1024, 'mime' => 'video/mp4'])
        ->assertCreated()
        ->assertJsonPath('data.total_chunks', 3)
        ->assertJsonPath('data.received_chunks', []);
});

it('rejects non-video files and oversized uploads', function () {
    $lecture = videoLecture();

    $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/lectures/{$lecture->id}/video-uploads", ['file_name' => 'a.pdf', 'file_size' => 100, 'mime' => 'application/pdf'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('mime', 'error.fields');

    $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/lectures/{$lecture->id}/video-uploads", ['file_name' => 'a.mp4', 'file_size' => 5 * 1024 ** 3, 'mime' => 'video/mp4'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('file_size', 'error.fields');
});

it('rejects chunks of the wrong size', function () {
    $lecture = videoLecture();
    config(['media.uploads.chunk_size' => 1024]);
    $session = $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/lectures/{$lecture->id}/video-uploads", ['file_name' => 'a.mp4', 'file_size' => 3000, 'mime' => 'video/mp4'])
        ->json('data');

    $this->actingAs($lecture->course->instructor)
        ->call('PUT', "/api/v1/instructor/uploads/{$session['id']}/chunks/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], str_repeat('x', 10))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'CHUNK_SIZE_MISMATCH');
});

it('refuses to complete until every chunk has arrived', function () {
    $lecture = videoLecture();
    config(['media.uploads.chunk_size' => 1024]);
    $session = $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/lectures/{$lecture->id}/video-uploads", ['file_name' => 'a.mp4', 'file_size' => 2048, 'mime' => 'video/mp4'])
        ->json('data');

    $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/uploads/{$session['id']}/complete")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'UPLOAD_INCOMPLETE');
});

it('keeps upload sessions private to their owner', function () {
    $lecture = videoLecture();
    $session = $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/lectures/{$lecture->id}/video-uploads", ['file_name' => 'a.mp4', 'file_size' => 2048, 'mime' => 'video/mp4'])
        ->json('data');

    $this->actingAs(CourseFactory::instructor())
        ->getJson("/api/v1/instructor/uploads/{$session['id']}")
        ->assertNotFound();
});

it('marks garbage uploads as failed instead of processing them', function () {
    $lecture = videoLecture();
    $id = sendChunks($this, $lecture, str_repeat('This is a spreadsheet export, not a video. ', 3500), 'fake.mp4');

    if (! app(Ffmpeg::class)->available()) {
        $this->markTestSkipped('ffmpeg is not installed');
    }

    $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/uploads/{$id}/complete")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'NOT_A_VIDEO');

    expect(VideoAsset::query()->first()->status)->toBe(VideoStatus::Failed);
    Queue::assertNothingPushed();
});

it('assembles a real video, attaches it and queues processing', function () {
    $file = sampleVideo() ?? $this->markTestSkipped('ffmpeg is not installed');
    $lecture = videoLecture();

    $id = sendChunks($this, $lecture, file_get_contents($file));
    $this->actingAs($lecture->course->instructor)
        ->postJson("/api/v1/instructor/uploads/{$id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'processing');

    $asset = VideoAsset::query()->firstOrFail();
    expect($lecture->fresh()->video_asset_id)->toBe($asset->id)
        ->and(md5(Storage::disk('local')->get($asset->source_path)))->toBe(md5_file($file));
    Queue::assertPushedOn('media', ProcessVideo::class);
    @unlink($file);
});

it('transcodes into encrypted HLS with a poster and updates course totals', function () {
    $file = sampleVideo() ?? $this->markTestSkipped('ffmpeg is not installed');
    $lecture = videoLecture();
    $id = sendChunks($this, $lecture, file_get_contents($file));
    $this->actingAs($lecture->course->instructor)->postJson("/api/v1/instructor/uploads/{$id}/complete")->assertOk();

    $asset = VideoAsset::query()->firstOrFail();
    (new ProcessVideo($asset))->handle(app(VideoProvider::class), app(CourseStats::class));

    $asset->refresh();
    $disk = Storage::disk('local');
    $playlist = $disk->get("{$asset->hls_path}/360p.m3u8");

    expect($asset->status)->toBe(VideoStatus::Ready)
        ->and($asset->duration_seconds)->toBe(3)
        ->and(strlen(base64_decode($asset->encryption_key)))->toBe(16)
        ->and($asset->renditions)->toHaveCount(1) // 360p source → only the 360p rendition
        ->and($disk->exists("{$asset->hls_path}/master.m3u8"))->toBeTrue()
        ->and($disk->exists($asset->poster_path))->toBeTrue()
        ->and($playlist)->toContain('#EXT-X-KEY:METHOD=AES-128,URI="erudsway-key"')
        // The key is never written next to the segments.
        ->and(collect($disk->allFiles($asset->hls_path))->filter(fn ($f) => str_ends_with($f, '.key')))->toBeEmpty()
        ->and($lecture->fresh()->duration_seconds)->toBe(3)
        ->and(Course::query()->find($lecture->course_id)->duration_seconds)->toBe(3);
    @unlink($file);
});
