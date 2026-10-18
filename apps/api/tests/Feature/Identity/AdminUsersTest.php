<?php

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Models\AuditLog;

it('restricts user management to staff with the permission', function () {
    $this->actingAs(makeStaff(Permission::ReviewInstructorApplications))->getJson('/api/v1/admin/users')->assertForbidden();
    $this->actingAs(makeStaff(Permission::ManageUsers))->getJson('/api/v1/admin/users')->assertOk();
});

it('searches users by name or email and filters by role', function () {
    User::factory()->create(['name' => 'Kavya Iyer', 'email' => 'kavya@example.com']);
    makeUser(Role::Instructor)->update(['name' => 'Rohan Das']);

    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->getJson('/api/v1/admin/users?q=KAVYA')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'kavya@example.com');

    $this->actingAs($admin)->getJson('/api/v1/admin/users?role=instructor')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Rohan Das');
});

it('suspends a user, which ends their access immediately', function () {
    $student = makeUser();
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/users/{$student->uuid}/suspend", ['reason' => 'Payment fraud'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect(AuditLog::query()->where('action', 'user.suspended')->where('subject_id', (string) $student->id)->first()?->after)
        ->toMatchArray(['status' => 'suspended', 'reason' => 'Payment fraud']);

    $this->actingAs($student->fresh())
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
});

it('requires a reason to suspend', function () {
    $this->actingAs(makeUser(Role::Admin))
        ->postJson('/api/v1/admin/users/'.makeUser()->uuid.'/suspend')
        ->assertUnprocessable();
});

it('protects admins and the acting user from suspension', function () {
    $admin = makeUser(Role::Admin);
    $otherAdmin = makeUser(Role::Admin);

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$admin->uuid}/suspend", ['reason' => 'Testing'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'CANNOT_SUSPEND_SELF');

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$otherAdmin->uuid}/suspend", ['reason' => 'Testing'])
        ->assertUnprocessable()->assertJsonPath('error.code', 'CANNOT_SUSPEND_ADMIN');
});

it('reactivates a suspended user', function () {
    $user = User::factory()->suspended()->create();

    $this->actingAs(makeUser(Role::Admin))
        ->postJson("/api/v1/admin/users/{$user->uuid}/reactivate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});
