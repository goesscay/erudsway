<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Enums\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local development data only. Roles and permissions come from migrations;
     * production admins are created with `php artisan identity:promote-admin`.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command->warn('DatabaseSeeder only runs in local/testing environments.');

            return;
        }

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@erudsway.test'],
            ['name' => 'Local Admin', 'password' => 'password'],
        );
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->profile()->firstOrCreate();
        $admin->syncRoles([Role::Admin->value]);
    }
}
