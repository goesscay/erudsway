<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\AuditLogger;

it('records actor, subject and request details', function () {
    $admin = makeUser(Role::Admin);
    $subject = makeUser();
    $this->actingAs($admin);

    $entry = app(AuditLogger::class)->record('user.test', $subject, ['a' => 1], ['a' => 2]);

    expect($entry->actor_id)->toBe($admin->id)
        ->and($entry->subject_id)->toBe((string) $subject->id)
        ->and($entry->before)->toBe(['a' => 1])
        ->and($entry->ip)->toBe('127.0.0.1');
});

it('cannot be edited or deleted', function () {
    $entry = app(AuditLogger::class)->record('user.test');

    expect(fn () => $entry->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(AuditLog::query()->find($entry->id)->action)->toBe('user.test');
});

it('promotes a verified user to admin from the console', function () {
    $user = makeUser();

    $this->artisan('identity:promote-admin', ['email' => strtoupper($user->email)])->assertSuccessful();

    expect($user->fresh()->hasRole('admin'))->toBeTrue();
});

it('refuses to promote an unverified user', function () {
    $user = makeUser(verified: false);

    $this->artisan('identity:promote-admin', ['email' => $user->email])->assertFailed();

    expect($user->fresh()->hasRole('admin'))->toBeFalse();
});
