<?php

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Media\Enums\VideoStatus;
use App\Modules\Media\Models\VideoAsset;
use Database\Factories\CourseFactory;

/*
| Shared helpers for course-building tests (loaded from tests/Pest.php).
| Tests using them must create the "registration-returns" subcategory first.
*/

function draftFor(?User $instructor = null): Course
{
    $instructor ??= CourseFactory::instructor();
    $course = Course::factory()->create([
        'instructor_id' => $instructor->id,
        'category_id' => Category::query()->where('slug', 'registration-returns')->value('id'),
    ]);
    $course->sections()->create(['title' => 'Introduction', 'position' => 1]);

    return $course;
}

/** A course that passes every checklist item. */
function readyCourse(): Course
{
    $course = draftFor();
    $course->forceFill([
        'title' => 'GST Returns in Practice',
        'subtitle' => 'Monthly and annual GST returns, step by step',
        'description' => '<p>'.str_repeat('A practical walkthrough of GST returns. ', 10).'</p>',
        'thumbnail_path' => 'course-thumbnails/x.jpg',
        'outcomes' => ['File GSTR-1', 'File GSTR-3B', 'Reconcile ITC', 'File GSTR-9'],
        'requirements' => ['Basic accounting'],
        'audience' => ['Accountants'],
        'pricing_confirmed_at' => now(),
    ])->save();

    $section = $course->sections()->first();
    foreach (range(1, 5) as $i) {
        $asset = VideoAsset::query()->create(['owner_id' => $course->instructor_id, 'provider' => 'local_hls', 'original_name' => "l{$i}.mp4"]);
        $asset->forceFill(['status' => VideoStatus::Ready, 'duration_seconds' => 420])->save();
        $lecture = $section->lectures()->create(['course_id' => $course->id, 'title' => "Lecture {$i}", 'kind' => 'video', 'position' => $i, 'duration_seconds' => 420]);
        $lecture->forceFill(['video_asset_id' => $asset->id])->save();
    }

    return $course->fresh();
}
