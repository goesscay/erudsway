<?php

use App\Modules\Identity\Notifications\VerifyEmailQueued;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

function verificationUrl($user, ?string $hash = null, int $minutes = 60): string
{
    return URL::temporarySignedRoute('identity.verification.verify', now()->addMinutes($minutes), [
        'id' => $user->uuid,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

it('verifies the email from the signed link and redirects to the web app', function () {
    $user = makeUser(verified: false);

    $this->get(verificationUrl($user))->assertRedirect('http://localhost:3000/verify-email?status=verified');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a link with the wrong hash', function () {
    $user = makeUser(verified: false);

    $this->get(verificationUrl($user, sha1('someone-else@example.com')))
        ->assertRedirect('http://localhost:3000/verify-email?status=invalid');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired link', function () {
    $user = makeUser(verified: false);
    $url = verificationUrl($user, minutes: 60);

    $this->travel(61)->minutes();

    $this->get($url)->assertRedirect('http://localhost:3000/verify-email?status=invalid');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email on request', function () {
    Notification::fake();
    $user = makeUser(verified: false);

    $this->actingAs($user)->postJson('/api/v1/auth/email/resend')->assertOk();

    Notification::assertSentTo($user, VerifyEmailQueued::class);
});
