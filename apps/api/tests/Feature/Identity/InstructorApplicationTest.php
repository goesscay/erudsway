<?php

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\InstructorApplication;
use App\Modules\Identity\Notifications\InstructorApplicationReviewed;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(fn () => Notification::fake());

function applicationPayload(array $overrides = []): array
{
    return array_merge([
        'headline' => 'Chartered Accountant and GST trainer',
        'topics' => ['Accounting', 'GST'],
        'experience' => str_repeat('Twelve years in audit and indirect tax practice, training junior staff. ', 3),
        'teaching_experience' => 'classroom',
        'sample_url' => 'https://example.com/my-sample-lesson',
    ], $overrides);
}

describe('applying', function () {
    it('requires a verified email', function () {
        $this->actingAs(makeUser(verified: false))
            ->postJson('/api/v1/instructor/applications', applicationPayload())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    });

    it('creates a pending application', function () {
        $user = makeUser();

        $this->actingAs($user)
            ->postJson('/api/v1/instructor/applications', applicationPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.topics', ['Accounting', 'GST']);

        $this->actingAs($user)->getJson('/api/v1/instructor/applications/latest')->assertJsonPath('data.status', 'pending');
    });

    it('allows only one pending application at a time', function () {
        $user = makeUser();
        InstructorApplication::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson('/api/v1/instructor/applications', applicationPayload())
            ->assertConflict()
            ->assertJsonPath('error.code', 'APPLICATION_PENDING');
    });

    it('turns away existing instructors', function () {
        $this->actingAs(makeUser(Role::Instructor))
            ->postJson('/api/v1/instructor/applications', applicationPayload())
            ->assertConflict()
            ->assertJsonPath('error.code', 'ALREADY_INSTRUCTOR');
    });

    it('validates the application', function (array $overrides, string $field) {
        $this->actingAs(makeUser())
            ->postJson('/api/v1/instructor/applications', applicationPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor($field, 'error.fields');
    })->with([
        'short experience' => [['experience' => 'Too short'], 'experience'],
        'no topics' => [['topics' => []], 'topics'],
        'too many topics' => [['topics' => ['a1', 'b2', 'c3', 'd4', 'e5', 'f6']], 'topics'],
        'unknown experience level' => [['teaching_experience' => 'guru'], 'teaching_experience'],
        'insecure sample link' => [['sample_url' => 'http://example.com'], 'sample_url'],
    ]);

    it('returns null when the user has never applied', function () {
        $this->actingAs(makeUser())->getJson('/api/v1/instructor/applications/latest')->assertExactJson(['data' => null]);
    });
});

describe('reviewing', function () {
    it('restricts the review queue to staff with the permission', function (?Closure $actor, int $status) {
        InstructorApplication::factory()->create();
        $request = $actor ? $this->actingAs($actor()) : $this;

        $request->getJson('/api/v1/admin/instructor-applications')->assertStatus($status);
    })->with([
        'guest' => [null, 401],
        'student' => [fn () => makeUser(), 403],
        'instructor' => [fn () => makeUser(Role::Instructor), 403],
        'staff without permission' => [fn () => makeStaff(Permission::ManageUsers), 403],
        'reviewer staff' => [fn () => makeStaff(Permission::ReviewInstructorApplications), 200],
        'admin' => [fn () => makeUser(Role::Admin), 200],
    ]);

    it('lists pending applications oldest first', function () {
        $older = InstructorApplication::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = InstructorApplication::factory()->create(['created_at' => now()->subDay()]);

        $this->actingAs(makeUser(Role::Admin))
            ->getJson('/api/v1/admin/instructor-applications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $older->uuid)
            ->assertJsonPath('data.1.id', $newer->uuid)
            ->assertJsonPath('meta.total', 2);
    });

    it('approves: grants the instructor role, creates a profile, audits and notifies', function () {
        $application = InstructorApplication::factory()->create();
        $applicant = $application->user;
        $admin = makeUser(Role::Admin);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/instructor-applications/{$application->uuid}/approve", ['notes' => 'Welcome!'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.reviewer.id', $admin->uuid);

        $applicant->refresh();
        expect($applicant->isInstructor())->toBeTrue()
            ->and($applicant->instructorProfile->slug)->toBe(Str::slug($applicant->name))
            ->and(AuditLog::query()->where('action', 'instructor_application.approved')->where('actor_id', $admin->id)->exists())->toBeTrue();
        Notification::assertSentTo($applicant, InstructorApplicationReviewed::class);
    });

    it('gives instructors with the same name distinct profile slugs', function () {
        $admin = makeUser(Role::Admin);
        $first = InstructorApplication::factory()->for(User::factory()->state(['name' => 'Asha Rao']))->create();
        $second = InstructorApplication::factory()->for(User::factory()->state(['name' => 'Asha Rao']))->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/instructor-applications/{$first->uuid}/approve")->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/admin/instructor-applications/{$second->uuid}/approve")->assertOk();

        expect($first->user->fresh()->instructorProfile->slug)->toBe('asha-rao')
            ->and($second->user->fresh()->instructorProfile->slug)->toBe('asha-rao-2');
    });

    it('does not let staff review their own application', function () {
        $staff = makeStaff(Permission::ReviewInstructorApplications);
        $own = InstructorApplication::factory()->for($staff)->create();

        $this->actingAs($staff)
            ->postJson("/api/v1/admin/instructor-applications/{$own->uuid}/approve")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'SELF_REVIEW');

        expect($own->fresh()->isPending())->toBeTrue();
    });

    it('cannot review the same application twice', function () {
        $application = InstructorApplication::factory()->create();
        $admin = makeUser(Role::Admin);
        $this->actingAs($admin)->postJson("/api/v1/admin/instructor-applications/{$application->uuid}/approve")->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/instructor-applications/{$application->uuid}/reject", ['notes' => 'Changed my mind'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'APPLICATION_ALREADY_REVIEWED');
    });

    it('requires notes to reject, and lets the applicant apply again afterwards', function () {
        $application = InstructorApplication::factory()->create();
        $admin = makeUser(Role::Admin);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/instructor-applications/{$application->uuid}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('notes', 'error.fields');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/instructor-applications/{$application->uuid}/reject", ['notes' => 'Please add a sample lesson.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        expect($application->user->fresh()->isInstructor())->toBeFalse();

        $this->actingAs($application->user)
            ->postJson('/api/v1/instructor/applications', applicationPayload())
            ->assertCreated();
    });
});
