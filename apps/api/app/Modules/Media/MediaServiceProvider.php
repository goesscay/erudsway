<?php

namespace App\Modules\Media;

use App\Modules\Media\Http\Controllers\SegmentController;
use App\Modules\Media\Video\LocalHlsProvider;
use App\Modules\Media\Video\VideoProvider;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class MediaServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function register(): void
    {
        $this->app->bind(VideoProvider::class, fn ($app) => match (config('media.video_provider')) {
            'local_hls' => $app->make(LocalHlsProvider::class),
            default => throw new InvalidArgumentException('Unknown video provider: '.config('media.video_provider')),
        });
    }

    public function boot(): void
    {
        parent::boot();

        // Signed segment URLs (Nginx serves these in production; see SegmentController).
        if (! $this->app->routesAreCached()) {
            Route::get('hls/{uuid}/{file}', SegmentController::class)
                ->where(['uuid' => '[0-9a-f-]{36}', 'file' => '[A-Za-z0-9_.]+'])
                ->name('media.segment');
        }
    }
}
