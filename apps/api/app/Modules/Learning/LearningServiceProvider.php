<?php

namespace App\Modules\Learning;

use App\Support\Modules\ModuleServiceProvider;

class LearningServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
