<?php

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;

it('signs in with valid credentials and records the login time', function () {
    $user = makeUser();

    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.id', $user->uuid);

    expect($user->fresh()->last_login_at)->not->toBeNull();
    $this->withHeaders(spaHeaders())->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $user->email);
});

it('gives the same message for an unknown email and a wrong password', function () {
    $user = makeUser();

    $wrong = $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope'])
        ->assertUnprocessable()
        ->json('error.fields.email.0');

    $unknown = $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope'])
        ->assertUnprocessable()
        ->json('error.fields.email.0');

    expect($wrong)->toBe($unknown)->toBe('These credentials do not match our records.');
});

it('refuses suspended accounts', function () {
    $user = User::factory()->suspended()->create();

    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');

    $this->assertGuest('web');
});

it('locks out repeated failed attempts for an email', function () {
    $user = makeUser();

    foreach (range(1, 5) as $_) {
        $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(429);
});

it('signs out', function () {
    $user = makeUser();
    $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);

    $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->assertGuest('web');
});

it('requires authentication for /me', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('exposes roles and permissions on /me', function () {
    $admin = makeUser(Role::Admin);

    $this->actingAs($admin)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.roles', ['admin'])
        ->assertJsonFragment(['permissions' => array_map(
            fn ($p) => $p->value,
            Permission::cases(),
        )]);
});
