<?php

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Console\PromoteAdmin;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

class IdentityServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();

        $this->configureRateLimits();
        $this->configureEmailLinks();

        if ($this->app->runningInConsole()) {
            $this->commands([PromoteAdmin::class]);
        }
    }

    private function configureRateLimits(): void
    {
        $emailKey = fn (Request $r) => mb_strtolower((string) $r->input('email')).'|'.$r->ip();

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by($emailKey($r)),
            Limit::perMinute(20)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perHour(5)->by($emailKey($r)));
        RateLimiter::for('verification', fn (Request $r) => Limit::perMinute(6)->by($r->user()?->getAuthIdentifier() ?? $r->ip()));
    }

    /** Links in auth emails point at the web app (reset) or a signed API route that redirects there (verify). */
    private function configureEmailLinks(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $frontend = rtrim((string) config('app.frontend_url'), '/');

            return $frontend.'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]);
        });

        VerifyEmail::createUrlUsing(fn (User $user) => URL::temporarySignedRoute(
            'identity.verification.verify',
            now()->addMinutes(60),
            ['id' => $user->uuid, 'hash' => sha1($user->getEmailForVerification())],
        ));
    }
}
