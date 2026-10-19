<?php

namespace App\Modules\Commerce;

use App\Modules\Commerce\Console\ReconcilePayments;
use App\Modules\Commerce\Payments\FakeGateway;
use App\Modules\Commerce\Payments\PaymentGateway;
use App\Modules\Commerce\Payments\RazorpayGateway;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

class CommerceServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app) {
            return match (config('commerce.gateway')) {
                'razorpay' => $app->make(RazorpayGateway::class),
                'fake' => tap($app->make(FakeGateway::class), fn () => FakeGateway::guardAgainstProduction()),
                default => throw new InvalidArgumentException('Unknown payment gateway: '.config('commerce.gateway')),
            };
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/resources/views', 'commerce');

        // PLAN §9: checkout 10/min per user.
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->getAuthIdentifier() ?? $r->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcilePayments::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('commerce:reconcile')->dailyAt('03:30')->timezone('Asia/Kolkata')->withoutOverlapping();
        });
    }
}
