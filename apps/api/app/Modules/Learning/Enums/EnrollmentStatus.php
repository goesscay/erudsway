<?php

namespace App\Modules\Learning\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Refunded = 'refunded';
    case Revoked = 'revoked';
}
