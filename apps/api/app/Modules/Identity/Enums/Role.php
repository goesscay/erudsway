<?php

namespace App\Modules\Identity\Enums;

enum Role: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Admin = 'admin';
    /**
     * Held together with Admin. The only role that can make, remove or change admins
     * (including other super admins); otherwise the same as an admin.
     */
    case SuperAdmin = 'super_admin';
}
