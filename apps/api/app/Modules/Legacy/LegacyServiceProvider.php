<?php

namespace App\Modules\Legacy;

use App\Modules\Legacy\Console\ImportMoodleCourse;
use App\Modules\Legacy\Console\ImportMoodleLearners;
use App\Support\Modules\ModuleServiceProvider;

/**
 * One-off moves from the old Moodle site: a course with its videos, quizzes and notes, then
 * its buyers with their progress and certificates. Every created record is remembered in
 * `legacy_links`, so the commands can be re-run safely and finish an interrupted import.
 */
class LegacyServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([ImportMoodleCourse::class, ImportMoodleLearners::class]);
        }
    }
}
