<?php

namespace App\Modules\Commerce\Enums;

enum RefundStatus: string
{
    case Requested = 'requested';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
