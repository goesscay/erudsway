<?php

use App\Modules\Assessment\AssessmentServiceProvider;
use App\Modules\Authoring\AuthoringServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Certificates\CertificatesServiceProvider;
use App\Modules\Commerce\CommerceServiceProvider;
use App\Modules\Engagement\EngagementServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Learning\LearningServiceProvider;
use App\Modules\Media\MediaServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    PlatformServiceProvider::class,
    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    MediaServiceProvider::class,
    AuthoringServiceProvider::class,
    LearningServiceProvider::class,
    CommerceServiceProvider::class,
    FinanceServiceProvider::class,
    EngagementServiceProvider::class,
    AssessmentServiceProvider::class,
    CertificatesServiceProvider::class,
];
