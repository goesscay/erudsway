<?php

namespace App\Modules\Media\Video;

use App\Modules\Media\Models\VideoAsset;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a viewer's own copy of the stored playlists: variant and key URIs carry their
 * playback token, and every segment URL is individually signed and expires with the token.
 * Nothing in the stored files is ever served directly.
 */
class HlsPlaylists
{
    public function __construct(private readonly SegmentSigner $signer) {}

    public function master(VideoAsset $asset, PlaybackToken $token): string
    {
        $lines = explode("\n", $this->read($asset, 'master.m3u8'));
        foreach ($lines as $i => $line) {
            if (preg_match('/^(\d{3,4})p\.m3u8$/', trim($line), $m)) {
                $lines[$i] = $this->apiUrl($asset, "{$m[1]}p.m3u8", $token);
            }
        }

        return implode("\n", $lines);
    }

    public function variant(VideoAsset $asset, int $height, PlaybackToken $token): string
    {
        $heights = array_column($asset->renditions ?? [], 'height');
        if (! in_array($height, $heights, true)) {
            throw new DomainException('NOT_FOUND', 'No such rendition.', 404);
        }

        $keyUrl = $this->apiUrl($asset, 'key', $token);
        $lines = explode("\n", $this->read($asset, "{$height}p.m3u8"));
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (str_starts_with($line, '#EXT-X-KEY')) {
                $lines[$i] = str_replace('URI="'.LocalHlsProvider::KEY_URI_PLACEHOLDER.'"', 'URI="'.$keyUrl.'"', $line);
            } elseif ($line !== '' && ! str_starts_with($line, '#')) {
                $lines[$i] = $this->signer->url($asset->uuid, basename($line), $token->expiresAt);
            }
        }

        return implode("\n", $lines);
    }

    public function posterUrl(VideoAsset $asset, PlaybackToken $token): ?string
    {
        return $asset->poster_path ? $this->signer->url($asset->uuid, 'poster.jpg', $token->expiresAt) : null;
    }

    public function masterUrl(VideoAsset $asset, PlaybackToken $token): string
    {
        return $this->apiUrl($asset, 'master.m3u8', $token);
    }

    private function apiUrl(VideoAsset $asset, string $file, PlaybackToken $token): string
    {
        return url("/api/v1/stream/{$asset->uuid}/{$file}").'?token='.$token->encode();
    }

    private function read(VideoAsset $asset, string $file): string
    {
        $path = "{$asset->hls_path}/{$file}";
        if (! $asset->isReady() || $asset->hls_path === null || ! Storage::disk('local')->exists($path)) {
            throw new DomainException('VIDEO_UNAVAILABLE', 'This video isn’t available.', 404);
        }

        return (string) Storage::disk('local')->get($path);
    }
}
