<?php

namespace App\Modules\Learning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Learning\Services\Entitlements;
use App\Modules\Learning\Services\ProgressTracker;
use App\Modules\Media\Models\VideoAsset;
use App\Modules\Media\Video\HlsPlaylists;
use App\Modules\Media\Video\PlaybackToken;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Playback flow (PLAN §6): the player asks for a lecture → we check entitlement and hand
 * back a per-viewer playlist URL with a short-lived token → the playlists and key are
 * generated per request and re-check entitlement → segments come from Nginx, signed.
 */
class PlaybackController extends Controller
{
    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly HlsPlaylists $playlists,
        private readonly ProgressTracker $progress,
    ) {}

    /** Start (or refresh) playback of a video lecture. Free previews work signed out. */
    public function issue(Request $request, Lecture $lecture): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');
        if ($needed = $this->entitlements->lockedBehind($user, $lecture)) {
            throw Entitlements::lockedError($needed);
        }
        if (! $this->entitlements->canView($user, $lecture)) {
            throw $user === null
                ? new DomainException('UNAUTHENTICATED', 'Log in to watch this lecture.', 401)
                : new DomainException('NOT_ENROLLED', 'Enroll in this course to watch this lecture.', 403);
        }

        $asset = $lecture->videoAsset;
        if ($lecture->kind !== LectureKind::Video || $asset === null || ! $asset->isReady()) {
            throw new DomainException('VIDEO_UNAVAILABLE', 'This lecture has no video yet.', 422);
        }

        // Editions with a view limit: each full view of a video uses one.
        $enrollment = $user ? $this->entitlements->enrollment($user, $lecture->course) : null;
        $viewsLeft = $enrollment ? $this->progress->viewsLeft($enrollment, $lecture) : null;
        if ($viewsLeft !== null && $viewsLeft <= 0) {
            throw new DomainException('WATCH_LIMIT_REACHED', 'You’ve used all your full views of this video. Upgrade your edition to watch it again.', 403);
        }

        $token = PlaybackToken::issue($asset->uuid, $lecture->id, $user?->id);

        return ApiResponse::data([
            'lecture_id' => $lecture->id,
            'playlist_url' => $this->playlists->masterUrl($asset, $token),
            'poster_url' => $this->playlists->posterUrl($asset, $token),
            'duration_seconds' => $asset->duration_seconds,
            /** Full views left under the edition's limit; null = unlimited. */
            'views_left' => $viewsLeft,
            'expires_at' => date(DATE_ATOM, $token->expiresAt),
            // Shown faintly over the video to discourage screen recording.
            'watermark' => $user === null ? null : $user->email,
        ]);
    }

    public function master(Request $request, string $asset): Response
    {
        [$video, $token] = $this->authorize($request, $asset);

        return $this->playlist($this->playlists->master($video, $token));
    }

    public function variant(Request $request, string $asset, int $height): Response
    {
        [$video, $token] = $this->authorize($request, $asset);

        return $this->playlist($this->playlists->variant($video, $height, $token));
    }

    /** The 16-byte AES-128 key, only after re-checking that the viewer may still watch. */
    public function key(Request $request, string $asset): Response
    {
        [$video] = $this->authorize($request, $asset);
        $key = base64_decode((string) $video->encryption_key, true);
        abort_if($key === false || strlen($key) !== 16, 404);

        return response($key, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array{VideoAsset, PlaybackToken} */
    private function authorize(Request $request, string $assetUuid): array
    {
        $token = PlaybackToken::decode((string) $request->query('token'), $assetUuid);
        $video = VideoAsset::query()->where('uuid', $assetUuid)->firstOrFail();
        $lecture = Lecture::query()->with('course')->find($token->lectureId);
        $user = $token->userId !== null ? User::query()->find($token->userId) : null;

        $allowed = $lecture !== null
            && $lecture->video_asset_id === $video->id
            && ($token->userId === null || $user !== null)
            && $this->entitlements->canView($user, $lecture);
        if (! $allowed) {
            throw new DomainException('PLAYBACK_DENIED', 'You no longer have access to this video.', 403);
        }

        return [$video, $token];
    }

    private function playlist(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
