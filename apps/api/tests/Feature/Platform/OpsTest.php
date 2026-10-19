<?php

use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Notifications\OpsAlert;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

it('reports readiness, including the database and cache', function () {
    $this->getJson('/api/v1/health')->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.checks', ['database' => true, 'cache' => true]);
});

it('emails the operator once per problem, and again only after it clears', function () {
    Notification::fake();
    config()->set('app.ops_alert_email', 'ops@example.test');
    config()->set('app.ops_disk_min_percent', 0); // don't depend on this machine's disk

    $this->artisan('ops:check')->assertSuccessful();
    Notification::assertNothingSent();

    DB::table('failed_jobs')->insert(['uuid' => (string) str()->uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);
    $this->artisan('ops:check')->assertSuccessful();
    $this->artisan('ops:check')->assertSuccessful(); // same problem: not repeated
    Notification::assertSentTimes(OpsAlert::class, 1);
    Notification::assertSentTo(new AnonymousNotifiable, OpsAlert::class, function (OpsAlert $n, array $channels, AnonymousNotifiable $to) {
        return $to->routes['mail'] === 'ops@example.test' && str_contains($n->problems[0], 'failed in the last hour');
    });

    DB::table('failed_jobs')->delete();
    $this->artisan('ops:check'); // clears
    DB::table('failed_jobs')->insert(['uuid' => (string) str()->uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'boom', 'failed_at' => now()]);
    $this->artisan('ops:check');
    Notification::assertSentTimes(OpsAlert::class, 2);

    $this->artisan('ops:check --dry-run')->expectsOutputToContain('failed_jobs:')->assertSuccessful();
});

it('lets the operator vouch for an email when promoting the first admin', function () {
    $user = makeUser(verified: false);
    $this->artisan('identity:promote-admin', ['email' => $user->email])->assertFailed();
    $this->artisan('identity:promote-admin', ['email' => $user->email, '--mark-verified' => true])->assertSuccessful();

    expect($user->fresh())->hasVerifiedEmail()->toBeTrue()
        ->and($user->fresh()->hasRole(Role::Admin->value))->toBeTrue();
});
