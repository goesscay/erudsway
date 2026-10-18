<?php

namespace App\Modules\Identity\Enums;

enum Role: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Admin = 'admin';
}
