<?php

namespace App\Modules\Fulfilment;

use App\Modules\Fulfilment\Console\SetupSimulatorPlans;
use App\Modules\Fulfilment\Console\SweepSimulatorAccess;
use App\Modules\Fulfilment\Listeners\CreateFulfilmentTasks;
use App\Modules\Learning\Events\EditionGranted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

/**
 * What an edition promises beyond the course itself: GST simulator access (provisioned
 * automatically through the simulator's integration API) and a faculty doubt session
 * (requested by the learner, arranged by staff). Each is a task in the admin queue.
 */
class FulfilmentServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(EditionGranted::class, CreateFulfilmentTasks::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SetupSimulatorPlans::class, SweepSimulatorAccess::class]);
        }

        // Ended trials and plans lose their simulator login; learners are reminded before that.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('simulator:sweep')->hourly()->withoutOverlapping();
        });
    }
}
