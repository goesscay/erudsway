<?php

namespace App\Modules\Finance\Enums;

enum PayoutStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
