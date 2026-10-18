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
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->profile()->create();
            $user->assignRole(Role::Student->value);

            return $user;
        });

        // Sends the verification email (Laravel's built-in listener).
        event(new Registered($user));

        return $user;
    }
}
