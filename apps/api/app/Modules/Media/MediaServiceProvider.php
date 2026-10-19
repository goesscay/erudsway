<?php

namespace App\Modules\Media;

use App\Modules\Media\Video\LocalHlsProvider;
use App\Modules\Media\Video\VideoProvider;
use App\Support\Modules\ModuleServiceProvider;
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
}
