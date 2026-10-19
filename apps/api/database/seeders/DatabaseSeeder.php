<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\InstructorProfile;
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

        // A local instructor for trying the course builder.
        $instructor = User::query()->firstOrCreate(
            ['email' => 'instructor@erudsway.test'],
            ['name' => 'CA Local Instructor', 'password' => 'password'],
        );
        $instructor->forceFill(['email_verified_at' => now()])->save();
        $instructor->profile()->firstOrCreate();
        $instructor->syncRoles([Role::Student->value, Role::Instructor->value]);
        InstructorProfile::query()->updateOrCreate(['user_id' => $instructor->id], [
            'slug' => 'local-instructor',
            'display_name' => 'CA Local Instructor',
            'headline' => 'Test account for local development',
            'approved_at' => now(),
        ]);

        // A local learner for trying enrollment and the course player.
        $learner = User::query()->firstOrCreate(
            ['email' => 'learner@erudsway.test'],
            ['name' => 'Local Learner', 'password' => 'password'],
        );
        $learner->forceFill(['email_verified_at' => now()])->save();
        $learner->profile()->firstOrCreate();
        $learner->syncRoles([Role::Student->value]);

        $this->call(CatalogDemoSeeder::class);
    }
}
