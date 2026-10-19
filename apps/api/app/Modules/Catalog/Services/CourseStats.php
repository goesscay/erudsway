<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\Lecture;

/** Keeps the denormalised lecture count and total length on courses in step with the curriculum. */
class CourseStats
{
    public function refresh(Course|int $course): void
    {
        $id = $course instanceof Course ? $course->id : $course;

        Course::query()->whereKey($id)->update([
            'lecture_count' => Lecture::query()->where('course_id', $id)->count(),
            'duration_seconds' => (int) Lecture::query()->where('course_id', $id)->sum('duration_seconds'),
        ]);
    }
}
