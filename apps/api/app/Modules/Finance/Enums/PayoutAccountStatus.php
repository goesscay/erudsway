<?php

namespace App\Modules\Finance\Enums;

enum PayoutAccountStatus: string
{
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
