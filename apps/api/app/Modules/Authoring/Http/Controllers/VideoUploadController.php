<?php

namespace App\Modules\Authoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Catalog\Enums\LectureKind;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Media\Models\UploadSession;
use App\Modules\Media\Models\VideoAsset;
use App\Modules\Media\Services\ChunkedUploads;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Resumable chunked video uploads:
 *   1. POST /instructor/lectures/{lecture}/video-uploads  → session (chunk size, chunk count)
 *   2. PUT  /instructor/uploads/{session}/chunks/{index}  → raw bytes, any order, retry-safe
 *   3. POST /instructor/uploads/{session}/complete         → assembles, validates, starts processing
 * GET /instructor/uploads/{session} lists received chunks so an interrupted upload can resume.
 */
class VideoUploadController extends Controller
{
    public function __construct(private readonly ChunkedUploads $uploads) {}

    public function start(Request $request, Lecture $lecture): JsonResponse
    {
        Gate::authorize('update', $lecture->course);
        if ($lecture->kind !== LectureKind::Video) {
            throw new DomainException('NOT_A_VIDEO_LECTURE', 'Videos can only be added to video lectures.', 422);
        }

        $data = $request->validate([
            'file_name' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1', 'max:'.config('media.uploads.max_bytes')],
            'mime' => ['required', 'string', Rule::in(config('media.uploads.video_mimes'))],
        ], [
            'file_size.max' => 'Videos can be at most 4 GB.',
            'mime.in' => 'Upload an MP4, MOV, MKV, WebM or AVI video.',
        ]);

        $session = $this->uploads->start($this->user($request), $lecture, $data['file_name'], (int) $data['file_size'], $data['mime']);

        return ApiResponse::data($this->sessionPayload($session), status: 201);
    }

    public function show(Request $request, UploadSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return ApiResponse::data($this->sessionPayload($session));
    }

    public function chunk(Request $request, UploadSession $session, int $index): JsonResponse
    {
        $this->authorizeSession($request, $session);
        $stream = $request->getContent(true);

        $session = $this->uploads->storeChunk($session, $index, $stream);

        return ApiResponse::data(['received' => count($session->received_chunks), 'total' => $session->total_chunks]);
    }

    public function complete(Request $request, UploadSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        $asset = $this->uploads->complete($session);

        return ApiResponse::data($this->assetPayload($asset));
    }

    /** Processing status of one of the instructor's videos. */
    public function asset(Request $request, string $uuid): JsonResponse
    {
        $asset = VideoAsset::query()->where('uuid', $uuid)->where('owner_id', $this->user($request)->id)->firstOrFail();

        return ApiResponse::data($this->assetPayload($asset));
    }

    private function authorizeSession(Request $request, UploadSession $session): void
    {
        if ($session->user_id !== $this->user($request)->id) {
            abort(404);
        }
        Gate::authorize('update', $session->lecture->course);
    }

    /** @return array<string, mixed> */
    private function sessionPayload(UploadSession $session): array
    {
        return [
            'id' => $session->uuid,
            'chunk_size' => $session->chunk_size,
            'total_chunks' => $session->total_chunks,
            'received_chunks' => $session->received_chunks,
            'expires_at' => $session->expires_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function assetPayload(VideoAsset $asset): array
    {
        return [
            'id' => $asset->uuid,
            'status' => $asset->status->value,
            'original_name' => $asset->original_name,
            'duration_seconds' => $asset->duration_seconds,
            'failure_reason' => $asset->failure_reason,
        ];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
