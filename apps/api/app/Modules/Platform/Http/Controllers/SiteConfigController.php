<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class SiteConfigController extends Controller
{
    /**
     * What the website needs before it renders: whether to load Google Analytics, and with which
     * Measurement ID. Only ever the public ID, never a secret. A short cache keeps an admin's
     * change from taking long to reach visitors.
     *
     * @unauthenticated
     */
    public function __invoke(): JsonResponse
    {
        $id = (string) config('services.google_analytics.measurement_id');
        $on = (bool) config('services.google_analytics.enabled') && preg_match('/^G-[A-Z0-9]{6,14}$/', $id) === 1;

        return ApiResponse::data(['google_analytics' => ['enabled' => $on, 'measurement_id' => $on ? $id : null]])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
