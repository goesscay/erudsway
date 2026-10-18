<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Liveness check for load balancers and uptime monitors.
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        return ApiResponse::data([
            'status' => 'ok',
            'version' => config('app.version'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
