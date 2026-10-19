<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Production also rejects passwords found in known breaches (checked via k-anonymity, no password leaves the server).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        // The web app's server-side rendering calls the API over loopback for every visitor;
        // it mustn't share one bucket. (Nginx only trusts Cloudflare to set the client IP,
        // so outside callers can't appear as loopback.)
        RateLimiter::for('api', fn (Request $request) => in_array($request->ip(), ['127.0.0.1', '::1'], true) && $request->user() === null
            ? Limit::none()
            : Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
