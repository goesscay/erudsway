<?php

namespace App\Modules\Identity\Console;

use App\Models\User;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * Bootstraps the first administrator: register normally through the web app,
 * then run `php artisan identity:promote-admin you@example.com` on the server.
 */
class PromoteAdmin extends Command
{
    protected $signature = 'identity:promote-admin
        {email : Email of an existing account}
        {--mark-verified : Also mark the email verified (for servers that can’t send mail yet)}
        {--super : Make them a super admin (admin plus managing other admins)}';

    protected $description = 'Grant the admin (or super admin) role to an existing user';

    public function handle(AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if (! $user->hasVerifiedEmail() && $this->option('mark-verified')) {
            $user->markEmailAsVerified();
            $audit->record('user.email_verified', $user, after: ['via' => 'console']);
            $this->line("Marked {$email} as verified.");
        }

        if (! $user->hasVerifiedEmail()) {
            $this->error('Verify the email address before granting admin access (or pass --mark-verified).');

            return self::FAILURE;
        }

        $role = $this->option('super') ? Role::SuperAdmin : Role::Admin;
        if ($user->hasRole($role->value) && $user->hasRole(Role::Admin->value)) {
            $this->info("{$email} is already ".($role === Role::SuperAdmin ? 'a super admin.' : 'an admin.'));

            return self::SUCCESS;
        }

        $user->assignRole(array_values(array_unique([Role::Admin->value, $role->value])));
        $audit->record('user.promoted_admin', $user, after: ['role' => $role->value, 'via' => 'console']);
        $this->info("{$email} is now ".($role === Role::SuperAdmin ? 'a super admin.' : 'an admin.'));

        return self::SUCCESS;
    }
}
