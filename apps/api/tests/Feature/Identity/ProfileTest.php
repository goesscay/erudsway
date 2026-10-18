<?php

use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Hash;

it('updates name and profile details', function () {
    $user = makeUser();

    $this->actingAs($user)
        ->patchJson('/api/v1/me', ['name' => 'Priya N.', 'headline' => 'Data analyst', 'phone' => '+91 98765 43210'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Priya N.')
        ->assertJsonPath('data.profile.headline', 'Data analyst')
        ->assertJsonPath('data.profile.phone', '+91 98765 43210');
});

it('validates profile fields', function () {
    $this->actingAs(makeUser())
        ->patchJson('/api/v1/me', ['phone' => 'call me', 'timezone' => 'Mars/Base'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('phone', 'error.fields')
        ->assertJsonValidationErrorFor('timezone', 'error.fields');
});

it('changes the password only with the current password, and audits it', function () {
    $user = makeUser();

    $this->actingAs($user)
        ->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'brandnew11', 'password_confirmation' => 'brandnew11'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('current_password', 'error.fields');

    $this->withHeaders(spaHeaders())->actingAs($user)
        ->putJson('/api/v1/me/password', ['current_password' => 'password', 'password' => 'brandnew11', 'password_confirmation' => 'brandnew11'])
        ->assertNoContent();

    expect(Hash::check('brandnew11', $user->fresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.password_changed')->where('actor_id', $user->id)->exists())->toBeTrue();
});
