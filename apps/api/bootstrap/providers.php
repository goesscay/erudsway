<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
];
