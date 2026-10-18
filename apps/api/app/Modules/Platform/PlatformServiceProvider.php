<?php

namespace App\Modules\Platform;

use App\Support\Modules\ModuleServiceProvider;

class PlatformServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
