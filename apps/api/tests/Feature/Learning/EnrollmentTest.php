<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Notifications\CourseAccessGranted;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

it('enrolls learners in free courses, once', function () {
    $course = liveCourse(free: true);
    $learner = makeUser();

    $this->actingAs($learner)->postJson("/api/v1/courses/{$course->slug}/enroll")
        ->assertCreated()
        ->assertJsonPath('data.source', 'free')
        ->assertJsonPath('data.course.slug', $course->slug);
    $this->actingAs($learner)->postJson("/api/v1/courses/{$course->slug}/enroll")->assertOk();

    expect(Enrollment::query()->count())->toBe(1)
        ->and($course->fresh()->enrollment_count)->toBe(1);
});

it('refuses free enrollment in paid, unpublished or own courses', function () {
    $paid = liveCourse(free: false);
    $this->actingAs(makeUser())->postJson("/api/v1/courses/{$paid->slug}/enroll")
        ->assertStatus(402)->assertJsonPath('error.code', 'COURSE_NOT_FREE');

    $draft = draftFor();
    $this->actingAs(makeUser())->postJson("/api/v1/courses/{$draft->slug}/enroll")->assertNotFound();

    $own = liveCourse(free: true);
    $this->actingAs($own->instructor)->postJson("/api/v1/courses/{$own->slug}/enroll")->assertUnprocessable();
});

it('requires sign-in to enroll', function () {
    $course = liveCourse();
    $this->postJson("/api/v1/courses/{$course->slug}/enroll")->assertUnauthorized();
});

it('lists My Learning, most recently studied first', function () {
    $learner = makeUser();
    $older = enrollIn(liveCourse(), $learner);
    $recent = enrollIn(liveCourse(), $learner);
    $older->forceFill(['last_accessed_at' => now()->subDay()])->save();
    $recent->forceFill(['last_accessed_at' => now()])->save();
    enrollIn(liveCourse())->forceFill(['status' => EnrollmentStatus::Active])->save(); // someone else's

    $this->actingAs($learner)->getJson('/api/v1/me/enrollments')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $recent->id);
});

it('reports enrollment status for the course page', function () {
    $course = liveCourse();
    $learner = makeUser();

    $this->actingAs($learner)->getJson("/api/v1/me/courses/{$course->slug}/enrollment")->assertJsonPath('data.enrolled', false);
    enrollIn($course, $learner);
    $this->actingAs($learner)->getJson("/api/v1/me/courses/{$course->slug}/enrollment")
        ->assertJsonPath('data.enrolled', true)
        ->assertJsonPath('data.role', 'learner');
});

it('lets user managers grant and revoke access with a reason, audited', function () {
    $course = liveCourse(free: false);
    $learner = makeUser();
    $admin = makeStaff(Permission::ManageUsers);

    $this->actingAs(makeStaff(Permission::ModerateCourses))
        ->postJson("/api/v1/admin/users/{$learner->uuid}/enrollments", ['course' => $course->slug, 'reason' => 'Scholarship batch'])
        ->assertForbidden();

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$learner->uuid}/enrollments", ['course' => $course->slug])
        ->assertUnprocessable()->assertJsonPath('error.fields.reason.0', fn ($m) => $m !== null);

    $id = $this->actingAs($admin)
        ->postJson("/api/v1/admin/users/{$learner->uuid}/enrollments", ['course' => $course->slug, 'reason' => 'Scholarship batch'])
        ->assertCreated()
        ->assertJsonPath('data.source', 'admin_grant')
        ->json('data.id');
    Notification::assertSentTo($learner, CourseAccessGranted::class);

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$learner->uuid}/enrollments", ['course' => $course->slug, 'reason' => 'Again please'])
        ->assertStatus(409);

    $this->actingAs($admin)->postJson("/api/v1/admin/enrollments/{$id}/revoke", ['reason' => 'Granted by mistake'])
        ->assertOk()->assertJsonPath('data.status', 'revoked');
    $this->actingAs($admin)->getJson("/api/v1/admin/users/{$learner->uuid}/enrollments")->assertJsonCount(1, 'data');

    expect(AuditLog::query()->whereIn('action', ['enrollment.granted', 'enrollment.revoked'])->where('actor_id', $admin->id)->count())->toBe(2)
        ->and($course->fresh()->enrollment_count)->toBe(0);
});

it('keeps revoked learners out of free re-enrollment until an admin restores access', function () {
    $course = liveCourse(free: true);
    $learner = makeUser();
    enrollIn($course, $learner)->forceFill(['status' => EnrollmentStatus::Revoked])->save();

    $this->actingAs($learner)->postJson("/api/v1/courses/{$course->slug}/enroll")
        ->assertForbidden()->assertJsonPath('error.code', 'ENROLLMENT_REVOKED');

    $this->actingAs(makeStaff(Permission::ManageUsers))
        ->postJson("/api/v1/admin/users/{$learner->uuid}/enrollments", ['course' => $course->slug, 'reason' => 'Support ticket 4411'])
        ->assertCreated()->assertJsonPath('data.status', 'active');
});
