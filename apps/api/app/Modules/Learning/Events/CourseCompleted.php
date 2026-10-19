<?php

namespace App\Modules\Learning\Events;

use App\Modules\Learning\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;

/** A learner has just completed every lecture of a course. */
class CourseCompleted
{
    use Dispatchable;

    public function __construct(public readonly Enrollment $enrollment) {}
}
