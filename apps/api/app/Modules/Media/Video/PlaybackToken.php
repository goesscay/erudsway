<?php

namespace App\Modules\Media\Video;

use App\Support\Http\DomainException;

/**
 * A short-lived, signed grant to stream one lecture's video, bound to the viewer
 * (null for a signed-out visitor watching a free preview). It travels in the playlist
 * and key URLs, so no cookie is needed by the player.
 */
final class PlaybackToken
{
    public function __construct(
        public readonly string $assetUuid,
        public readonly int $lectureId,
        public readonly ?int $userId,
        public readonly int $expiresAt,
    ) {}

    public static function issue(string $assetUuid, int $lectureId, ?int $userId): self
    {
        $ttl = (int) config('media.stream.token_ttl_minutes') * 60;

        return new self($assetUuid, $lectureId, $userId, now()->getTimestamp() + $ttl);
    }

    public function encode(): string
    {
        $payload = self::base64url((string) json_encode(['a' => $this->assetUuid, 'l' => $this->lectureId, 'u' => $this->userId, 'e' => $this->expiresAt]));

        return $payload.'.'.self::base64url(hash_hmac('sha256', $payload, self::key(), true));
    }

    /** Throws a 403 for anything forged, expired or minted for another video. */
    public static function decode(string $token, string $assetUuid): self
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');
        $expected = self::base64url(hash_hmac('sha256', $payload, self::key(), true));
        if ($payload === '' || ! hash_equals($expected, $signature)) {
            throw new DomainException('PLAYBACK_DENIED', 'This playback link is not valid.', 403);
        }

        /** @var array{a?: string, l?: int, u?: int|null, e?: int}|null $data */
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        if (! is_array($data) || ($data['a'] ?? null) !== $assetUuid || ! isset($data['l'], $data['e'])) {
            throw new DomainException('PLAYBACK_DENIED', 'This playback link is not valid.', 403);
        }
        if ($data['e'] < now()->getTimestamp()) {
            throw new DomainException('PLAYBACK_EXPIRED', 'This playback link has expired.', 403);
        }

        return new self($data['a'], (int) $data['l'], isset($data['u']) ? (int) $data['u'] : null, (int) $data['e']);
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'erudsway-playback', (string) config('app.key'), true);
    }

    private static function base64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
