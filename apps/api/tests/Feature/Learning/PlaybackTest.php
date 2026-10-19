<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Media\Video\PlaybackToken;
use App\Modules\Media\Video\SegmentSigner;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

function firstLecture(): Lecture
{
    $course = liveCourse(free: false);
    $lecture = $course->lectures()->orderBy('position')->firstOrFail();
    fakeHls($lecture->videoAsset);

    return $lecture->fresh();
}

/** Query string → path relative to the app, for following URLs the API hands out. */
function relative(string $url): string
{
    return (string) preg_replace('#^https?://[^/]+#', '', $url);
}

it('refuses playback to people without access', function () {
    $lecture = firstLecture();

    $this->postJson("/api/v1/lectures/{$lecture->id}/playback")->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
    $this->actingAs(makeUser())->postJson("/api/v1/lectures/{$lecture->id}/playback")->assertForbidden()->assertJsonPath('error.code', 'NOT_ENROLLED');
});

it('lets anyone watch a free preview, signed in or not', function () {
    $lecture = firstLecture();
    $lecture->forceFill(['is_free_preview' => true])->save();

    $this->postJson("/api/v1/lectures/{$lecture->id}/playback")
        ->assertOk()
        ->assertJsonPath('data.watermark', null);
});

it('serves an enrolled learner a per-viewer playlist chain and the key', function () {
    $lecture = firstLecture();
    $learner = makeUser();
    enrollIn($lecture->course, $learner);

    $playback = $this->actingAs($learner)->postJson("/api/v1/lectures/{$lecture->id}/playback")
        ->assertOk()
        ->assertJsonPath('data.watermark', $learner->email)
        ->json('data');

    // Master: variant URIs point back at the API with the same token.
    $master = $this->get(relative($playback['playlist_url']))->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')->getContent();
    expect($master)->not->toContain("\n720p.m3u8")->toContain('/api/v1/stream/');

    preg_match('#(https?://\S+/720p\.m3u8\?token=\S+)#', (string) $master, $m);
    $variant = $this->get(relative($m[1]))->assertOk()->getContent();

    // Variant: key URI rewritten; segments signed for Nginx secure_link.
    expect($variant)->not->toContain('erudsway-key')
        ->toContain('/api/v1/stream/'.$lecture->videoAsset->uuid.'/key?token=')
        ->toMatch('#/hls/[0-9a-f-]{36}/720p_0000\.ts\?md5=[A-Za-z0-9_-]+&expires=\d+#');

    preg_match('#URI="([^"]+)"#', (string) $variant, $k);
    $key = $this->get(relative($k[1]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($key->getContent())->toBe('0123456789abcdef');

    // Signed segment URL works through the local fallback; tampering does not.
    preg_match('#(https?://\S+/720p_0000\.ts\?\S+)#', (string) $variant, $s);
    $this->get(relative($s[1]))->assertOk();
    $this->get(str_replace('720p_0000', '360p_0000', relative($s[1])))->assertForbidden();
});

it('stops serving the key as soon as access is revoked', function () {
    $lecture = firstLecture();
    $learner = makeUser();
    $enrollment = enrollIn($lecture->course, $learner);
    $token = PlaybackToken::issue($lecture->videoAsset->uuid, $lecture->id, $learner->id)->encode();
    $keyUrl = "/api/v1/stream/{$lecture->videoAsset->uuid}/key?token={$token}";

    $this->get($keyUrl)->assertOk();
    $enrollment->forceFill(['status' => EnrollmentStatus::Revoked])->save();
    $this->get($keyUrl)->assertForbidden();
});

it('rejects forged, expired and mismatched tokens', function () {
    $lecture = firstLecture();
    $uuid = $lecture->videoAsset->uuid;
    $learner = makeUser();
    enrollIn($lecture->course, $learner);
    $good = PlaybackToken::issue($uuid, $lecture->id, $learner->id)->encode();

    $this->get("/api/v1/stream/{$uuid}/key?token=".substr($good, 0, -2).'xx')->assertForbidden();
    $this->get("/api/v1/stream/{$uuid}/key")->assertForbidden();

    // A token for another lecture's video can't unlock this one.
    $other = $lecture->course->lectures()->where('id', '!=', $lecture->id)->firstOrFail();
    $foreign = PlaybackToken::issue($other->videoAsset->uuid, $other->id, $learner->id)->encode();
    $this->get("/api/v1/stream/{$uuid}/key?token={$foreign}")->assertForbidden();

    $this->travel(3)->hours();
    $this->get("/api/v1/stream/{$uuid}/key?token={$good}")->assertForbidden()->assertJsonPath('error.code', 'PLAYBACK_EXPIRED');
});

it('never serves playlists or keys from the segment path', function () {
    $lecture = firstLecture();
    $signer = app(SegmentSigner::class);
    $uuid = $lecture->videoAsset->uuid;
    $expires = now()->addHour()->getTimestamp();

    $url = relative($signer->url($uuid, 'master.m3u8', $expires));
    $this->get($url)->assertNotFound();
    $this->get(relative($signer->url($uuid, 'poster.jpg', $expires)))->assertOk();
});

it('lets the instructor and course reviewers play unpublished courses', function () {
    $lecture = firstLecture();
    $lecture->course->forceFill(['status' => 'in_review', 'published_at' => null])->save();

    $this->actingAs($lecture->course->instructor)->postJson("/api/v1/lectures/{$lecture->id}/playback")->assertOk();
    $this->actingAs(makeStaff(Permission::ModerateCourses))
        ->postJson("/api/v1/lectures/{$lecture->id}/playback")->assertOk();
});
