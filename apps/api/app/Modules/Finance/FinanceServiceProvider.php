<?php

namespace App\Modules\Finance;

use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Commerce\Events\RefundProcessed;
use App\Modules\Finance\Console\GeneratePayouts;
use App\Modules\Finance\Console\ReleaseEarnings;
use App\Modules\Finance\Listeners\RecordRefund;
use App\Modules\Finance\Listeners\RecordSale;
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
        Event::listen(RefundProcessed::class, RecordRefund::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseEarnings::class, GeneratePayouts::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('finance:release-earnings')->dailyAt('02:00')->timezone('Asia/Kolkata')->withoutOverlapping();
            $schedule->command('finance:generate-payouts')->monthlyOn(1, '06:00')->timezone('Asia/Kolkata')->withoutOverlapping();
        });
    }
}
