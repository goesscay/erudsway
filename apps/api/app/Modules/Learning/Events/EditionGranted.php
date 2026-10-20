<?php

namespace App\Modules\Learning\Events;

use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;

/** A learner now has $enrollment->edition (bought, upgraded or given); $previous is what they had before. */
class EditionGranted
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment, public readonly ?CourseEdition $previous) {}
}
