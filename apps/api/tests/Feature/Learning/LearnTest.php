<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Models\Note;
use App\Modules\Media\Models\LectureResource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

function lecturesOf($course): array
{
    return $course->lectures()->orderBy('position')->get()->all();
}

it('gives enrolled learners the course outline with their progress', function () {
    $course = liveCourse(free: false);
    $learner = makeUser();
    enrollIn($course, $learner);
    [$first] = lecturesOf($course);
    $this->actingAs($learner)->postJson("/api/v1/lectures/{$first->id}/completion")->assertOk();

    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}")
        ->assertOk()
        ->assertJsonPath('data.role', 'learner')
        ->assertJsonPath('data.enrollment.completed_count', 1)
        ->assertJsonPath('data.enrollment.progress_percent', 20)
        ->assertJsonPath('data.curriculum.0.lectures.0.completed', true)
        ->assertJsonPath('data.curriculum.0.lectures.0.has_video', true);
});

it('tells non-enrolled visitors to enroll, and hides unpublished courses', function () {
    $course = liveCourse(free: false);
    $this->actingAs(makeUser())->getJson("/api/v1/learn/{$course->slug}")->assertForbidden()->assertJsonPath('error.code', 'NOT_ENROLLED');

    $draft = draftFor();
    $this->actingAs(makeUser())->getJson("/api/v1/learn/{$draft->slug}")->assertNotFound();
    $this->actingAs($draft->instructor)->getJson("/api/v1/learn/{$draft->slug}")->assertOk()->assertJsonPath('data.role', 'instructor');
    $this->actingAs(makeStaff(Permission::ModerateCourses))->getJson("/api/v1/learn/{$draft->slug}")->assertOk()->assertJsonPath('data.role', 'reviewer');
});

it('serves article content and resource downloads only to people with access', function () {
    Storage::fake('local');
    $course = liveCourse(free: false);
    [$first] = lecturesOf($course);
    $first->forceFill(['kind' => 'article', 'article_html' => '<p>Read me</p>'])->save();
    $file = UploadedFile::fake()->create('chart.pdf', 10, 'application/pdf');
    $resource = new LectureResource(['path' => $file->store('lecture-resources', 'local'), 'original_name' => 'chart.pdf', 'mime' => 'application/pdf', 'size_bytes' => 10240]);
    $first->resources()->save($resource);

    $outsider = makeUser();
    $this->actingAs($outsider)->getJson("/api/v1/learn/lectures/{$first->id}")->assertForbidden();
    $this->actingAs($outsider)->get("/api/v1/learn/resources/{$resource->id}/download")->assertForbidden();

    $learner = makeUser();
    enrollIn($course, $learner);
    $this->actingAs($learner)->getJson("/api/v1/learn/lectures/{$first->id}")
        ->assertOk()
        ->assertJsonPath('data.article_html', '<p>Read me</p>')
        ->assertJsonPath('data.resources.0.name', 'chart.pdf');
    $this->actingAs($learner)->get("/api/v1/learn/resources/{$resource->id}/download")
        ->assertOk()->assertDownload('chart.pdf');
});

it('tracks position, completes videos near the end and recounts progress', function () {
    $course = liveCourse();
    $learner = makeUser();
    $enrollment = enrollIn($course, $learner);
    [$first, $second] = lecturesOf($course); // 420 s each

    $this->actingAs($learner)->putJson("/api/v1/lectures/{$first->id}/progress", ['position_seconds' => 120])
        ->assertOk()
        ->assertJsonPath('data.completed', false)
        ->assertJsonPath('data.last_position_seconds', 120);

    // Seeking back keeps the furthest point; 90% in completes the lecture.
    $this->actingAs($learner)->putJson("/api/v1/lectures/{$first->id}/progress", ['position_seconds' => 390])
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.enrollment.progress_percent', 20);
    $this->actingAs($learner)->putJson("/api/v1/lectures/{$first->id}/progress", ['position_seconds' => 10])
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.last_position_seconds', 10);

    $this->actingAs($learner)->postJson("/api/v1/lectures/{$second->id}/completion")->assertJsonPath('data.enrollment.completed_count', 2);
    $this->actingAs($learner)->deleteJson("/api/v1/lectures/{$second->id}/completion")->assertJsonPath('data.enrollment.completed_count', 1);

    expect($enrollment->fresh()->last_lecture_id)->toBe($second->id);
});

it('marks the course complete when every lecture is done', function () {
    $course = liveCourse();
    $learner = makeUser();
    $enrollment = enrollIn($course, $learner);

    foreach (lecturesOf($course) as $lecture) {
        $this->actingAs($learner)->postJson("/api/v1/lectures/{$lecture->id}/completion")->assertOk();
    }

    expect($enrollment->fresh())
        ->progress_percent->toBe(100)
        ->completed_at->not->toBeNull();
});

it('does not track instructors previewing their own course', function () {
    $course = liveCourse();
    [$first] = lecturesOf($course);

    $this->actingAs($course->instructor)->putJson("/api/v1/lectures/{$first->id}/progress", ['position_seconds' => 30])
        ->assertOk()->assertJsonPath('data.tracked', false);
    $this->actingAs(makeUser())->putJson("/api/v1/lectures/{$first->id}/progress", ['position_seconds' => 30])->assertForbidden();
});

it('keeps notes private to their author', function () {
    $course = liveCourse();
    $learner = makeUser();
    enrollIn($course, $learner);
    /** @var Lecture $first */
    [$first] = lecturesOf($course);

    $id = $this->actingAs($learner)->postJson("/api/v1/lectures/{$first->id}/notes", ['body' => 'Table 4 excludes exempt supplies', 'at_seconds' => 95])
        ->assertCreated()
        ->assertJsonPath('data.at_seconds', 95)
        ->assertJsonPath('data.lecture_title', $first->title)
        ->json('data.id');

    $other = makeUser();
    enrollIn($course, $other);
    $this->actingAs($other)->getJson("/api/v1/learn/{$course->slug}/notes")->assertJsonCount(0, 'data');
    $this->actingAs($other)->patchJson("/api/v1/notes/{$id}", ['body' => 'mine now'])->assertNotFound();
    $this->actingAs($other)->deleteJson("/api/v1/notes/{$id}")->assertNotFound();

    $this->actingAs($learner)->patchJson("/api/v1/notes/{$id}", ['body' => 'Edited'])->assertJsonPath('data.body', 'Edited');
    $this->actingAs($learner)->getJson("/api/v1/learn/{$course->slug}/notes")->assertJsonCount(1, 'data');
    $this->actingAs($learner)->deleteJson("/api/v1/notes/{$id}")->assertNoContent();
    expect(Note::query()->count())->toBe(0);

    $this->actingAs(makeUser())->postJson("/api/v1/lectures/{$first->id}/notes", ['body' => 'x'])->assertForbidden();
});
