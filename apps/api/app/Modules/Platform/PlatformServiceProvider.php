<?php

namespace App\Modules\Platform;

use App\Modules\Platform\Console\OpsCheck;
use App\Modules\Platform\Services\Settings;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

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
        $settings = $this->app->make(Settings::class);
        $this->defineSettings($settings);
        $settings->apply();

        if ($this->app->runningInConsole()) {
            $this->commands([OpsCheck::class]);
        }
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('ops:check')->everyFiveMinutes()->withoutOverlapping();
        });
    }

    private function defineSettings(Settings $settings): void
    {
        $g = 'Google Analytics';
        $settings->define('services.google_analytics.enabled', $g, 'Track visits with Google Analytics', 'Turn off to stop sending any visit data to Google. The tag is never loaded on the admin console.', 'boolean', ['boolean']);
        $settings->define('services.google_analytics.measurement_id', $g, 'Measurement ID', 'From Google Analytics: Admin → Data streams → your web stream. Looks like G-ABC123XYZ9.', 'text', ['string', 'regex:/^G-[A-Z0-9]{6,14}$/']);
    }
}
