<?php

namespace App\Modules\Finance\Enums;

enum LedgerAccount: string
{
    case Instructor = 'instructor';
    case Platform = 'platform';
    case Tax = 'tax';
    case GatewayFee = 'gateway_fee';
}
