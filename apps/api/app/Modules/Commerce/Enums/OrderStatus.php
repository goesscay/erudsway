<?php

namespace App\Modules\Commerce\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}
