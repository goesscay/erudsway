<?php

namespace App\Modules\Identity\Enums;

enum TeachingExperience: string
{
    case None = 'none';
    case Informal = 'informal';
    case Classroom = 'classroom';
    case Online = 'online';
}
