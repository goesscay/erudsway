<?php

namespace App\Modules\Engagement;

use App\Support\Modules\ModuleServiceProvider;

class EngagementServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
