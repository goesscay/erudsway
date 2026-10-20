<?php

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Notifications\AccountInvite;
use App\Modules\Identity\Notifications\VerifyEmailQueued;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as RoleModel;

function makeSuperAdmin(): User
{
    $user = makeUser(Role::Admin);
    $user->assignRole(Role::SuperAdmin->value);

    return $user;
}

function makeRole(string $name, Permission ...$permissions): RoleModel
{
    $role = RoleModel::create(['name' => $name, 'guard_name' => 'web']);
    $role->syncPermissions(array_map(fn (Permission $p) => $p->value, $permissions));

    return $role;
}

it('lists built-in roles first, then custom roles with their holders', function () {
    $role = makeRole('Accountant', Permission::ManageFinance, Permission::ViewReports);
    makeUser()->assignRole($role);

    $response = $this->actingAs(makeStaff(Permission::ManageUsers))->getJson('/api/v1/admin/roles')->assertOk();

    expect(collect($response->json('data.items'))->pluck('name')->all())->toBe(['student', 'instructor', 'admin', 'super_admin', 'Accountant']);
    $accountant = collect($response->json('data.items'))->firstWhere('name', 'Accountant');
    expect($accountant)->toMatchArray(['built_in' => false, 'users_count' => 1, 'permissions' => ['manage_finance', 'view_reports']]);
    expect(collect($response->json('data.items'))->firstWhere('name', 'admin')['permissions'])->toHaveCount(count(Permission::cases()));
});

it('lets only super admins create, change and delete roles', function () {
    $body = ['name' => 'Support', 'description' => 'Helps learners', 'permissions' => ['manage_users']];

    $this->actingAs(makeUser(Role::Admin))->postJson('/api/v1/admin/roles', $body)
        ->assertForbidden()->assertJsonPath('error.code', 'SUPER_ADMIN_ONLY');

    $super = makeSuperAdmin();
    $id = $this->actingAs($super)->postJson('/api/v1/admin/roles', $body)
        ->assertCreated()->assertJsonPath('data.name', 'Support')->json('data.id');

    $this->actingAs($super)->putJson("/api/v1/admin/roles/{$id}", [...$body, 'name' => 'Learner support', 'permissions' => ['manage_users', 'moderate_courses']])
        ->assertOk()->assertJsonPath('data.permissions', ['manage_users', 'moderate_courses']);

    expect(AuditLog::query()->where('action', 'role.updated')->first()?->before)->toMatchArray(['name' => 'Support']);

    $this->actingAs($super)->deleteJson("/api/v1/admin/roles/{$id}")->assertNoContent();
    expect(RoleModel::query()->whereKey($id)->exists())->toBeFalse();
});

it('keeps built-in roles and their names fixed', function () {
    $super = makeSuperAdmin();
    $admin = RoleModel::findByName('admin', 'web');

    $this->actingAs($super)->putJson("/api/v1/admin/roles/{$admin->id}", ['name' => 'Boss', 'description' => null, 'permissions' => []])
        ->assertUnprocessable()->assertJsonPath('error.code', 'BUILT_IN_ROLE');
    $this->actingAs($super)->deleteJson("/api/v1/admin/roles/{$admin->id}")
        ->assertUnprocessable()->assertJsonPath('error.code', 'BUILT_IN_ROLE');

    makeRole('Editor');
    foreach (['Admin', 'super admin', 'editor'] as $name) {
        $this->actingAs($super)->postJson('/api/v1/admin/roles', ['name' => $name, 'description' => null, 'permissions' => []])
            ->assertUnprocessable()->assertJsonValidationErrorFor('name', 'error.fields');
    }
});

it('gives a role’s permissions to its holders, and takes them away with the role', function () {
    $role = makeRole('Moderator', Permission::ModerateCourses);
    $user = makeUser();
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)->putJson("/api/v1/admin/users/{$user->uuid}/access", ['admin' => false, 'roles' => ['Moderator'], 'permissions' => ['view_reports']])
        ->assertOk()
        ->assertJsonPath('data.custom_roles', ['Moderator'])
        ->assertJsonPath('data.permission_sources.moderate_courses', ['Moderator'])
        ->assertJsonPath('data.permission_sources.view_reports', ['Direct']);

    $this->actingAs($user->fresh())->getJson('/api/v1/admin/course-submissions')->assertSuccessful();

    $this->actingAs($admin)->putJson("/api/v1/admin/users/{$user->uuid}/access", ['admin' => false, 'roles' => [], 'permissions' => []])
        ->assertOk()->assertJsonPath('data.custom_roles', []);
    expect($user->fresh()->getAllPermissions())->toBeEmpty();

    $this->actingAs($admin)->putJson("/api/v1/admin/users/{$user->uuid}/access", ['admin' => false, 'roles' => ['admin'], 'permissions' => []])
        ->assertUnprocessable()->assertJsonValidationErrorFor('roles.0', 'error.fields');
    expect($role->fresh())->not->toBeNull();
});

it('shows who holds each permission and through what', function () {
    $role = makeRole('Accountant', Permission::ManageFinance);
    $viaRole = makeUser();
    $viaRole->assignRole($role);
    $direct = makeStaff(Permission::ManageFinance);
    makeStaff(Permission::ViewReports);
    $admin = makeUser(Role::Admin);

    $counts = collect($this->actingAs($admin)->getJson('/api/v1/admin/permissions')->assertOk()->json('data.items'))->pluck('users_count', 'key');
    expect($counts['manage_finance'])->toBe(3);

    $holders = collect($this->actingAs($admin)->getJson('/api/v1/admin/permissions/manage_finance/users')->assertOk()->json('data.items'))->keyBy('id');
    expect($holders[$viaRole->uuid]['via'])->toBe(['Accountant'])
        ->and($holders[$direct->uuid]['via'])->toBe(['Direct'])
        ->and($holders[$admin->uuid]['via'])->toBe(['Administrator']);
});

it('creates an account and emails a link to set a password, which verifies the email', function () {
    Notification::fake();
    makeRole('Support', Permission::ManageUsers);

    $this->actingAs(makeUser(Role::Admin))->postJson('/api/v1/admin/users', ['name' => 'Meera Nair', 'email' => ' Meera@Example.com ', 'roles' => ['Support']])
        ->assertCreated()->assertJsonPath('data.email', 'meera@example.com')->assertJsonPath('data.email_verified', false);

    $user = User::query()->where('email', 'meera@example.com')->firstOrFail();
    expect($user->getRoleNames()->sort()->values()->all())->toBe(['Support', 'student']);

    $token = null;
    Notification::assertSentTo($user, AccountInvite::class, function (AccountInvite $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', ['email' => 'meera@example.com', 'token' => $token, 'password' => 'a-Strong-pass-2026', 'password_confirmation' => 'a-Strong-pass-2026'])
        ->assertOk();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect(Hash::check('a-Strong-pass-2026', $user->fresh()->password))->toBeTrue();
});

it('lets only admins give roles when creating an account', function () {
    makeRole('Support');

    $this->actingAs(makeStaff(Permission::ManageUsers))->postJson('/api/v1/admin/users', ['name' => 'Meera Nair', 'email' => 'meera@example.com', 'roles' => ['Support']])
        ->assertForbidden();
    $this->actingAs(makeStaff(Permission::ManageUsers))->postJson('/api/v1/admin/users', ['name' => 'Meera Nair', 'email' => 'meera@example.com'])
        ->assertCreated();
});

it('resends the invite only to people who haven’t signed in', function () {
    Notification::fake();
    $admin = makeUser(Role::Admin);
    $fresh = makeUser();
    $active = makeUser();
    $active->forceFill(['last_login_at' => now()])->save();

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$fresh->uuid}/invite")->assertOk();
    Notification::assertSentTo($fresh, AccountInvite::class);

    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$active->uuid}/invite")
        ->assertUnprocessable()->assertJsonPath('error.code', 'ALREADY_SIGNED_IN');
});

it('edits a name and email; a new email has to be verified again', function () {
    Notification::fake();
    $user = makeUser();

    $this->actingAs(makeStaff(Permission::ManageUsers))->patchJson("/api/v1/admin/users/{$user->uuid}", ['name' => 'Ravi Kumar', 'email' => 'RAVI@example.com'])
        ->assertOk()->assertJsonPath('data.email', 'ravi@example.com')->assertJsonPath('data.email_verified', false);

    Notification::assertSentTo($user, VerifyEmailQueued::class);
    expect(AuditLog::query()->where('action', 'user.details_changed')->first()?->after)->toMatchArray(['name' => 'Ravi Kumar']);

    $this->actingAs(makeStaff(Permission::ManageUsers))->patchJson('/api/v1/admin/users/'.makeUser()->uuid, ['email' => 'ravi@example.com'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('email', 'error.fields');
});

it('lets only a super admin edit an admin’s details', function () {
    $target = makeUser(Role::Admin);

    $this->actingAs(makeUser(Role::Admin))->patchJson("/api/v1/admin/users/{$target->uuid}", ['name' => 'New Name'])
        ->assertForbidden()->assertJsonPath('error.code', 'SUPER_ADMIN_ONLY');
    $this->actingAs(makeSuperAdmin())->patchJson("/api/v1/admin/users/{$target->uuid}", ['name' => 'New Name'])
        ->assertOk();
});

it('filters users by a custom role', function () {
    $role = makeRole('Accountant');
    makeUser()->assignRole($role);
    makeUser();

    $this->actingAs(makeUser(Role::Admin))->getJson('/api/v1/admin/users?role=Accountant')
        ->assertOk()->assertJsonCount(1, 'data');
});
