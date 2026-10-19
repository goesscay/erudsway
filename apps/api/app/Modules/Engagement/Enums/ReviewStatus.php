<?php

namespace App\Modules\Engagement\Enums;

/** Flagged reviews stay public until staff decide; hidden ones leave the course page and its rating. */
enum ReviewStatus: string
{
    case Visible = 'visible';
    case Flagged = 'flagged';
    case Hidden = 'hidden';
}
