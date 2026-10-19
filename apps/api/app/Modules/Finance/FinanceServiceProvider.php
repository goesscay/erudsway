<?php

namespace App\Modules\Finance;

use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Events\RefundProcessed;
use App\Modules\Finance\Console\GeneratePayouts;
use App\Modules\Finance\Console\ReleaseEarnings;
use App\Modules\Finance\Listeners\RecordRefund;
use App\Modules\Finance\Listeners\RecordSale;
use App\Modules\Platform\Services\Settings;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

class FinanceServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();

        // Synchronous on purpose: the ledger is written inside the payment/refund transaction.
        Event::listen(OrderPaid::class, RecordSale::class);
        $this->defineSettings($this->app->make(Settings::class));
        Event::listen(RefundProcessed::class, RecordRefund::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseEarnings::class, GeneratePayouts::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('finance:release-earnings')->dailyAt('02:00')->timezone('Asia/Kolkata')->withoutOverlapping();
            $schedule->command('finance:generate-payouts')->monthlyOn(1, '06:00')->timezone('Asia/Kolkata')->withoutOverlapping();
        });
    }

    private function defineSettings(Settings $settings): void
    {
        $g = 'Instructor payouts';
        $settings->define('finance.hold_days', $g, 'Earnings hold', 'Days an instructor’s earnings wait before they can be paid out. Keep it at least as long as the refund window.', 'integer', ['integer', 'between:0,120']);
        $settings->define('finance.payout_minimum', $g, 'Payout minimum', 'Balances below this roll over to the next month.', 'paise', ['integer', 'between:0,10000000']);
        $settings->define('finance.tds.section', $g, 'TDS section', 'Income-tax section TDS is deducted under, printed on payout statements.', 'text', ['string', 'regex:/^[0-9]{3}[A-Z]?(-[A-Z0-9]+)?$/']);
        $settings->define('finance.tds.rate_bps', $g, 'TDS rate', 'In basis points: 10 = 0.1%, 1000 = 10%.', 'bps', ['integer', 'between:0,3000']);
        $settings->define('finance.maker_checker', $g, 'Two-person payout approval', 'The person who drafts a payout batch can’t approve it.', 'boolean', ['boolean']);
    }
}
