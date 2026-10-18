<?php

use App\Modules\Identity\Notifications\ResetPasswordQueued;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => Notification::fake());

it('emails a reset link that points at the web app', function () {
    $user = makeUser();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPasswordQueued::class, function (ResetPasswordQueued $n) use ($user) {
        $url = $n->toMail($user)->actionUrl;

        return str_starts_with($url, 'http://localhost:3000/reset-password?token=')
            && str_contains($url, 'email='.urlencode($user->email));
    });
});

it('responds identically for unknown emails', function () {
    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => makeUser()->email])->json();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com'])->json();

    expect($unknown)->toBe($known);
    Notification::assertSentTimes(ResetPasswordQueued::class, 1);
});

it('resets the password with a valid token', function () {
    $user = makeUser();
    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newsecret99',
        'password_confirmation' => 'newsecret99',
    ])->assertOk();

    expect(Hash::check('newsecret99', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid token', function () {
    $user = makeUser();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'newsecret99',
        'password_confirmation' => 'newsecret99',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.token.0', 'This reset link is invalid or has expired. Please request a new one.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
