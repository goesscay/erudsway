<?php

use App\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailQueued;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

function registration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Priya Nair',
        'email' => 'Priya.Nair@Example.com',
        'password' => 'learning2026',
        'password_confirmation' => 'learning2026',
        'accept_terms' => true,
    ], $overrides);
}

it('creates a student account, signs them in and sends a verification email', function () {
    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/register', registration())
        ->assertCreated()
        ->assertJsonPath('data.email', 'priya.nair@example.com')
        ->assertJsonPath('data.email_verified', false)
        ->assertJsonPath('data.roles', ['student']);

    $user = User::query()->where('email', 'priya.nair@example.com')->firstOrFail();
    expect($user->profile)->not->toBeNull()
        ->and($user->last_login_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user, 'web');
    Notification::assertSentTo($user, VerifyEmailQueued::class);
});

it('treats email addresses case-insensitively for uniqueness', function () {
    User::factory()->create(['email' => 'priya.nair@example.com']);

    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/register', registration(['email' => 'PRIYA.NAIR@example.com']))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED')
        ->assertJsonPath('error.fields.email.0', 'An account with this email already exists. Try logging in instead.');
});

it('rejects weak or mismatched passwords', function (array $overrides, string $field) {
    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/register', registration($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor($field, 'error.fields');
})->with([
    'too short' => [['password' => 'abc12', 'password_confirmation' => 'abc12'], 'password'],
    'no numbers' => [['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'], 'password'],
    'mismatch' => [['password_confirmation' => 'different1'], 'password'],
    '18+ and terms not confirmed' => [['accept_terms' => false], 'accept_terms'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'missing name' => [['name' => ''], 'name'],
]);

it('rate limits registrations from one address', function () {
    foreach (range(1, 5) as $i) {
        $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/register', registration(['email' => "user{$i}@example.com"]));
        auth()->guard('web')->logout();
    }

    $this->withHeaders(spaHeaders())
        ->postJson('/api/v1/auth/register', registration(['email' => 'user6@example.com']))
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
});
