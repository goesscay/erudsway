<?php

namespace App\Modules\Assessment;

use App\Support\Modules\ModuleServiceProvider;

class AssessmentServiceProvider extends ModuleServiceProvider
{
    protected function modulePath(): string
    {
        return __DIR__;
    }
}
