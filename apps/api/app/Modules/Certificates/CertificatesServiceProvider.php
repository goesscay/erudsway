<?php

namespace App\Modules\Certificates;

use App\Modules\Certificates\Console\IssueMissingCertificates;
use App\Modules\Certificates\Listeners\IssueCertificate;
use App\Modules\Learning\Events\CourseCompleted;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class CertificatesServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadViewsFrom(__DIR__.'/resources/views', 'certificates');

        Event::listen(CourseCompleted::class, IssueCertificate::class);

        if ($this->app->runningInConsole()) {
            $this->commands([IssueMissingCertificates::class]);
        }
    }
}
