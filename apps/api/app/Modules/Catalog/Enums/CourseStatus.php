<?php

namespace App\Modules\Catalog\Enums;

enum CourseStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case ChangesRequested = 'changes_requested';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Archived = 'archived';
}
