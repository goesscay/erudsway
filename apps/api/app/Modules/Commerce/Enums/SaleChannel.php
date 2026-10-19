<?php

namespace App\Modules\Commerce\Enums;

enum SaleChannel: string
{
    case Organic = 'organic';
    case InstructorReferral = 'instructor_referral';
    case PlatformPromoted = 'platform_promoted';
}
