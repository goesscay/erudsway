<?php

namespace App\Modules\Finance\Enums;

enum LedgerStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Paid = 'paid';
}
