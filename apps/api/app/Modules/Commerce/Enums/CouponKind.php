<?php

namespace App\Modules\Commerce\Enums;

enum CouponKind: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FixedPrice = 'fixed_price';
}
