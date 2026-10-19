<?php

use App\Modules\Authoring\Models\CourseSubmission;
use App\Modules\Authoring\Notifications\CourseReviewed;
use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

function submitted(): CourseSubmission
{
    $course = readyCourse();
    test()->actingAs($course->instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/submit")->assertOk();

    return CourseSubmission::query()->where('course_id', $course->id)->firstOrFail();
}

it('restricts the review queue to moderators', function () {
    submitted();

    $this->actingAs(makeUser(Role::Instructor))->getJson('/api/v1/admin/course-submissions')->assertForbidden();
    $this->actingAs(makeStaff(Permission::ManageUsers))->getJson('/api/v1/admin/course-submissions')->assertForbidden();
    $this->actingAs(makeStaff(Permission::ModerateCourses))->getJson('/api/v1/admin/course-submissions')
        ->assertOk()->assertJsonCount(1, 'data');
});

it('lets moderators view the full draft course', function () {
    $submission = submitted();

    $this->actingAs(makeStaff(Permission::ModerateCourses))
        ->getJson("/api/v1/admin/courses/{$submission->course->uuid}")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_review')
        ->assertJsonCount(5, 'data.curriculum.0.lectures');
});

it('publishes on approval, audits and notifies the instructor', function () {
    $submission = submitted();
    $moderator = makeStaff(Permission::ModerateCourses);

    $this->actingAs($moderator)
        ->postJson("/api/v1/admin/course-submissions/{$submission->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $course = $submission->course->fresh();
    expect($course->status)->toBe(CourseStatus::Published)
        ->and($course->published_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'course.approved')->where('actor_id', $moderator->id)->exists())->toBeTrue();
    Notification::assertSentTo($submission->submitter, CourseReviewed::class);

    $this->getJson("/api/v1/courses/{$course->slug}")->assertOk();
});

it('requests changes with feedback, and the instructor can edit again', function () {
    $submission = submitted();

    $this->actingAs(makeStaff(Permission::ModerateCourses))
        ->postJson("/api/v1/admin/course-submissions/{$submission->id}/reject", ['feedback' => 'Too short'])
        ->assertUnprocessable();

    $this->actingAs(makeStaff(Permission::ModerateCourses))
        ->postJson("/api/v1/admin/course-submissions/{$submission->id}/reject", [
            'feedback' => 'Lecture 3 audio is very low. Please re-record and resubmit.',
        ])
        ->assertOk();

    $course = $submission->course->fresh();
    expect($course->status)->toBe(CourseStatus::ChangesRequested);

    $this->actingAs($course->instructor)
        ->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'GST Returns in Practice (2nd edition)'])
        ->assertOk()
        ->assertJsonPath('data.latest_submission.feedback', 'Lecture 3 audio is very low. Please re-record and resubmit.');
});

it('cannot decide the same submission twice', function () {
    $submission = submitted();
    $moderator = makeStaff(Permission::ModerateCourses);
    $this->actingAs($moderator)->postJson("/api/v1/admin/course-submissions/{$submission->id}/approve")->assertOk();

    $this->actingAs($moderator)
        ->postJson("/api/v1/admin/course-submissions/{$submission->id}/reject", ['feedback' => str_repeat('Changed my mind. ', 3)])
        ->assertConflict();
});

it('unpublishes live courses with a reason', function () {
    $submission = submitted();
    $admin = makeUser(Role::Admin);
    $this->actingAs($admin)->postJson("/api/v1/admin/course-submissions/{$submission->id}/approve")->assertOk();
    $course = $submission->course->fresh();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/courses/{$course->uuid}/unpublish", ['reason' => 'Copyright complaint under review'])
        ->assertOk()
        ->assertJsonPath('data.status', 'unpublished');

    $this->getJson("/api/v1/courses/{$course->slug}")->assertNotFound();
});
