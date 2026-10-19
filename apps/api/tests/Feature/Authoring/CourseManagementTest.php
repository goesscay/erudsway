<?php

use App\Modules\Catalog\Enums\CourseStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
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

function live(): Course
{
    $course = readyCourse();
    $course->forceFill(['status' => CourseStatus::Published, 'published_at' => now()])->save();

    return $course;
}

it('lists every course, whatever its state, only for catalogue managers', function () {
    $draft = draftFor();
    live();

    $this->actingAs(makeUser(Role::Instructor))->getJson('/api/v1/admin/courses')->assertForbidden();
    $this->actingAs(makeStaff(Permission::ModerateCourses))->getJson('/api/v1/admin/courses')->assertForbidden();

    $manager = makeStaff(Permission::ManageCourses);
    $this->actingAs($manager)->getJson('/api/v1/admin/courses')->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($manager)->getJson('/api/v1/admin/courses?status=draft')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $draft->uuid);
    $this->actingAs($manager)->getJson('/api/v1/admin/courses?q='.urlencode($draft->instructor->email))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.instructor.email', $draft->instructor->email);
    $this->actingAs($manager)->getJson('/api/v1/admin/courses?category=gst')->assertOk()->assertJsonCount(2, 'data');
});

it('lets a catalogue manager edit someone else’s course in the builder, and audits it', function () {
    $course = draftFor();
    $manager = makeStaff(Permission::ManageCourses);

    $this->actingAs($manager)->getJson("/api/v1/instructor/courses/{$course->uuid}")->assertOk();
    $this->actingAs($manager)->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'Fixed by the Erudsway team'])
        ->assertOk()->assertJsonPath('data.title', 'Fixed by the Erudsway team');
    $this->actingAs($manager)->postJson("/api/v1/instructor/courses/{$course->uuid}/sections", ['title' => 'Extra'])->assertOk();

    expect(AuditLog::query()->where('action', 'course.edited_by_staff')->where('actor_id', $manager->id)->pluck('after')->pluck('action')->all())
        ->toBe(['courses.update', 'sections.store']);

    // The owner editing their own course leaves no staff trail.
    $this->actingAs($course->instructor)->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'Back to mine'])->assertOk();
    expect(AuditLog::query()->where('action', 'course.edited_by_staff')->count())->toBe(2);
});

it('still keeps other instructors out of a course', function () {
    $course = draftFor();

    $this->actingAs(makeUser(Role::Instructor))->getJson("/api/v1/instructor/courses/{$course->uuid}")->assertNotFound();
    $this->actingAs(makeStaff(Permission::ModerateCourses))->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'Not allowed'])
        ->assertForbidden();
});

it('publishes a ready course without a review, but not an incomplete one', function () {
    $manager = makeStaff(Permission::ManageCourses);

    $this->actingAs($manager)->postJson('/api/v1/admin/courses/'.draftFor()->uuid.'/publish')
        ->assertUnprocessable()->assertJsonPath('error.code', 'COURSE_NOT_READY');

    $course = readyCourse();
    $this->actingAs($manager)->postJson("/api/v1/admin/courses/{$course->uuid}/publish")
        ->assertOk()->assertJsonPath('data.status', 'published');

    expect($course->fresh()->published_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'course.published_by_staff')->exists())->toBeTrue();
    $this->getJson("/api/v1/courses/{$course->slug}")->assertOk();
});

it('won’t publish around a pending review', function () {
    $course = readyCourse();
    $this->actingAs($course->instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/submit")->assertOk();

    $this->actingAs(makeStaff(Permission::ManageCourses))->postJson("/api/v1/admin/courses/{$course->uuid}/publish")
        ->assertStatus(409)->assertJsonPath('error.code', 'IN_REVIEW');
});

it('archives with a reason, locks editing, and restores as unpublished', function () {
    $course = live();
    $course->forceFill(['featured_at' => now()])->save();
    $manager = makeStaff(Permission::ManageCourses);

    $this->actingAs($manager)->postJson("/api/v1/admin/courses/{$course->uuid}/archive", ['reason' => 'short'])->assertUnprocessable();
    $this->actingAs($manager)->postJson("/api/v1/admin/courses/{$course->uuid}/archive", ['reason' => 'Superseded by the 2027 edition.'])
        ->assertOk()->assertJsonPath('data.status', 'archived')->assertJsonPath('data.featured', false);

    $this->getJson("/api/v1/courses/{$course->slug}")->assertNotFound();
    $this->actingAs($manager)->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'Edited while archived'])->assertForbidden();
    $this->actingAs($course->instructor)->patchJson("/api/v1/instructor/courses/{$course->uuid}", ['title' => 'Edited while archived'])->assertForbidden();

    $this->actingAs($manager)->postJson("/api/v1/admin/courses/{$course->uuid}/restore")
        ->assertOk()->assertJsonPath('data.status', 'unpublished');
    $this->actingAs($manager)->postJson("/api/v1/admin/courses/{$course->uuid}/publish")->assertOk()->assertJsonPath('data.status', 'published');
});

it('hands a course to another approved instructor', function () {
    $course = draftFor();
    $manager = makeStaff(Permission::ManageCourses);
    $student = makeUser();
    $other = makeUser(Role::Instructor);

    $this->actingAs($manager)->putJson("/api/v1/admin/courses/{$course->uuid}/instructor", ['email' => $student->email])
        ->assertUnprocessable()->assertJsonPath('error.code', 'NOT_AN_INSTRUCTOR');
    $this->actingAs($manager)->putJson("/api/v1/admin/courses/{$course->uuid}/instructor", ['email' => $course->instructor->email])
        ->assertUnprocessable()->assertJsonPath('error.code', 'SAME_INSTRUCTOR');

    $previous = $course->instructor;
    $this->actingAs($manager)->putJson("/api/v1/admin/courses/{$course->uuid}/instructor", ['email' => strtoupper($other->email)])
        ->assertOk()->assertJsonPath('data.instructor.email', $other->email);

    expect($course->fresh()->instructor_id)->toBe($other->id)
        ->and(AuditLog::query()->where('action', 'course.instructor_changed')->value('after'))->toBe(['instructor' => $other->uuid]);
    $this->actingAs($other)->getJson("/api/v1/instructor/courses/{$course->uuid}")->assertOk();
    $this->actingAs($previous)->getJson("/api/v1/instructor/courses/{$course->uuid}")->assertNotFound();
});

it('lets an admin build a course and then give it to an instructor', function () {
    $admin = makeUser(Role::Admin);

    $uuid = $this->actingAs($admin)->postJson('/api/v1/instructor/courses', ['title' => 'GST Practical Mastery', 'category' => 'registration-returns'])
        ->assertCreated()->json('data.id');
    $instructor = makeUser(Role::Instructor);

    $this->actingAs($admin)->putJson("/api/v1/admin/courses/{$uuid}/instructor", ['email' => $instructor->email])->assertOk();
    $this->actingAs($instructor)->getJson('/api/v1/instructor/courses')->assertOk()->assertJsonPath('data.0.id', $uuid);
});

it('features only live courses and lists them first by default', function () {
    $manager = makeStaff(Permission::ManageCourses);
    $draft = draftFor();
    $this->actingAs($manager)->putJson("/api/v1/admin/courses/{$draft->uuid}/featured", ['featured' => true])
        ->assertUnprocessable()->assertJsonPath('error.code', 'NOT_PUBLISHED');

    $popular = live();
    $popular->forceFill(['enrollment_count' => 500])->save();
    $quiet = live();
    $quiet->forceFill(['slug' => 'quiet-course', 'enrollment_count' => 3])->save();

    $this->getJson('/api/v1/courses?sort=popular')->assertOk()->assertJsonPath('data.0.id', $popular->uuid);

    $this->actingAs($manager)->putJson("/api/v1/admin/courses/{$quiet->uuid}/featured", ['featured' => true])
        ->assertOk()->assertJsonPath('data.featured', true);
    $this->getJson('/api/v1/courses?sort=popular')->assertOk()->assertJsonPath('data.0.id', $quiet->uuid);
    $this->getJson('/api/v1/courses?category=gst')->assertOk()->assertJsonPath('data.0.id', $quiet->uuid);
    // An explicit sort the learner picked isn't overridden.
    $this->getJson('/api/v1/courses?sort=newest')->assertOk();
    $this->actingAs($manager)->getJson('/api/v1/admin/courses?featured=1')->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($manager)->putJson("/api/v1/admin/courses/{$quiet->uuid}/featured", ['featured' => false])
        ->assertOk()->assertJsonPath('data.featured', false);
    expect(AuditLog::query()->whereIn('action', ['course.featured', 'course.unfeatured'])->count())->toBe(2);
});
