<?php

namespace App\Modules\Platform;

use App\Modules\Platform\Services\Settings;
use App\Support\Modules\ModuleServiceProvider;

class PlatformServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        parent::boot();
        // Admin-edited settings override config/*.php for the rest of the request.
        $this->app->make(Settings::class)->apply();
    }
}
