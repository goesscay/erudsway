<?php

namespace App\Modules\Catalog\Enums;

enum LectureKind: string
{
    case Video = 'video';
    case Article = 'article';
    case Quiz = 'quiz';
    case Assignment = 'assignment';
}
