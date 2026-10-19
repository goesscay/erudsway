<?php

namespace App\Modules\Analytics;

use App\Support\Modules\ModuleServiceProvider;

class AnalyticsServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
