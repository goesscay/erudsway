<?php

namespace App\Modules\Learning\Enums;

enum EnrollmentSource: string
{
    case Purchase = 'purchase';
    case Free = 'free';
    case Coupon = 'coupon';
    case AdminGrant = 'admin_grant';
}
