<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Readiness check for uptime monitors and the deploy script: answers 503 if the
     * database or the cache/session store can't be reached. Reveals nothing else.
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health:ping', 1, 10);

                return Cache::get('health:ping') === 1 ?: throw new \RuntimeException('cache read failed');
            }),
        ];
        $ok = ! in_array(false, $checks, true);

        return ApiResponse::data([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'version' => config('app.version'),
            'time' => now()->toIso8601String(),
        ])->setStatusCode($ok ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
