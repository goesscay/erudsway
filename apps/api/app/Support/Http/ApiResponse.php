<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;

/**
 * The one response envelope used by every /api/v1 endpoint.
 *
 *   success: { "data": ..., "meta": {...}? }
 *   failure: { "error": { "code": "SNAKE_UPPER", "message": "...", "fields": {...}? } }
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function data(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        $body = ['data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }

    /**
     * @param  array<string, list<string>>  $fields
     */
    public static function error(string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
