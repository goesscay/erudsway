<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\Role;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data, ?string $phone = null): User
    {
        $user = DB::transaction(function () use ($data, $phone) {
            $user = User::create($data);
            // Registration only succeeds with the 18+ / Terms confirmation; record when it was given.
            $user->forceFill(['terms_accepted_at' => now()])->save();
            $user->profile()->create(['phone' => $phone]);
            $user->assignRole(Role::Student->value);

            return $user;
        });

        // Sends the verification email (Laravel's built-in listener).
        event(new Registered($user));

        return $user;
    }
}
