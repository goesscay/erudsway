<?php

namespace App\Modules\Commerce;

use App\Modules\Commerce\Console\ReconcilePayments;
use App\Modules\Commerce\Payments\FakeGateway;
use App\Modules\Commerce\Payments\PaymentGateway;
use App\Modules\Commerce\Payments\RazorpayGateway;
use App\Modules\Commerce\Support\Gst;
use App\Modules\Platform\Services\Settings;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
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
        $this->defineSettings($this->app->make(Settings::class));

        // PLAN §9: checkout 10/min per user.
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->getAuthIdentifier() ?? $r->ip()));

        if ($this->app->runningInConsole()) {
            $this->commands([ReconcilePayments::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('commerce:reconcile')->dailyAt('03:30')->timezone('Asia/Kolkata')->withoutOverlapping();
        });
    }

    private function defineSettings(Settings $settings): void
    {
        $g = 'Invoices and GST';
        $settings->define('commerce.seller.name', $g, 'Seller name', 'Legal name printed on tax invoices.', 'text', ['string', 'min:3', 'max:120']);
        $settings->define('commerce.seller.gstin', $g, 'Seller GSTIN', 'Printed on every tax invoice. Must match the seller state.', 'text', ['string', 'size:15', function (string $attr, mixed $value, \Closure $fail) {
            if (! Gst::validGstin(strtoupper((string) $value), (string) config('commerce.seller.state_code'))) {
                $fail('That GSTIN isn’t valid for the seller state.');
            }
        }]);
        $settings->define('commerce.seller.state_code', $g, 'Seller state code', 'Two-digit GST state code. Decides CGST+SGST (same state) or IGST.', 'text', ['string', Rule::in(array_keys(Gst::STATES))]);
        $settings->define('commerce.seller.address', $g, 'Seller address', 'Registered address printed on invoices.', 'text', ['string', 'min:10', 'max:300']);
        $settings->define('commerce.seller.email', $g, 'Invoice contact email', 'Shown on invoices and receipts.', 'text', ['email', 'max:120']);
        $settings->define('commerce.gst.rate_bps', $g, 'GST rate', 'Prices include GST; tax is worked out backwards at this rate. In basis points: 1800 = 18%.', 'bps', ['integer', 'between:0,2800']);
        $settings->define('commerce.gst.sac', $g, 'SAC code', 'Service accounting code printed on invoices.', 'text', ['string', 'regex:/^\d{6}$/']);
        $settings->define('commerce.invoice_prefix', $g, 'Invoice number prefix', 'Numbers look like ERW/2627/00001. Changing it mid-year starts a new-looking series; the count carries on.', 'text', ['string', 'regex:/^[A-Z0-9]{2,5}$/']);

        $r = 'Refunds';
        $settings->define('commerce.refunds.window_days', $r, 'Refund window', 'Days after purchase a learner can ask for a refund.', 'integer', ['integer', 'between:0,90']);
        $settings->define('commerce.refunds.auto_approve_below_percent', $r, 'Auto-approve below progress', 'Refunds are approved automatically when the learner has completed less than this share of the course (%); otherwise staff review them.', 'integer', ['integer', 'between:0,100']);
    }
}
