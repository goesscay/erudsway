<?php

namespace App\Modules\Media\Video;

use RuntimeException;

/**
 * Signs segment URLs in the format of Nginx's `secure_link_md5 "$secure_link_expires$uri SECRET"`,
 * so Nginx can serve segments straight from disk without touching PHP.
 */
class SegmentSigner
{
    /** Only media segments and posters are ever served this way; playlists and keys never are. */
    public const FILE_PATTERN = '/^(\d{3,4}p_\d{4}\.ts|poster\.jpg)$/';

    public function url(string $assetUuid, string $file, int $expiresAt): string
    {
        $uri = "/hls/{$assetUuid}/{$file}";
        $base = rtrim((string) config('media.stream.segment_url'), '/');

        return "{$base}{$uri}?md5={$this->hash($uri, $expiresAt)}&expires={$expiresAt}";
    }

    public function valid(string $uri, string $md5, int $expiresAt): bool
    {
        return $expiresAt >= now()->getTimestamp() && hash_equals($this->hash($uri, $expiresAt), $md5);
    }

    private function hash(string $uri, int $expiresAt): string
    {
        return rtrim(strtr(base64_encode(md5("{$expiresAt}{$uri} {$this->secret()}", true)), '+/', '-_'), '=');
    }

    private function secret(): string
    {
        $secret = (string) config('media.stream.secure_link_secret');
        if ($secret !== '') {
            return $secret;
        }
        if (app()->isProduction()) {
            throw new RuntimeException('MEDIA_SECURE_LINK_SECRET must be set in production (it must match the Nginx config).');
        }

        // Local and test environments: a stable secret derived from the app key.
        return hash_hmac('sha256', 'erudsway-secure-link', (string) config('app.key'));
    }
}
