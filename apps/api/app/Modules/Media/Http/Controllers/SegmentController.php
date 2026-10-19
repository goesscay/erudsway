<?php

namespace App\Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Media\Video\SegmentSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Local-development stand-in for Nginx `secure_link`: same URL, same signature check.
 * In production Nginx answers /hls/ itself and this route is never reached.
 */
class SegmentController extends Controller
{
    public function __invoke(Request $request, SegmentSigner $signer, string $uuid, string $file): BinaryFileResponse
    {
        $expires = (int) $request->query('expires');
        $md5 = (string) $request->query('md5');
        abort_unless(preg_match(SegmentSigner::FILE_PATTERN, $file) === 1, 404);
        abort_unless($signer->valid("/hls/{$uuid}/{$file}", $md5, $expires), 403);

        $path = "video-hls/{$uuid}/{$file}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => str_ends_with($file, '.ts') ? 'video/mp2t' : 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
            'Access-Control-Allow-Origin' => (string) config('app.frontend_url'),
        ]);
    }
}
