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
    protected $signature = 'identity:promote-admin {email : Email of an existing, verified account}';

    protected $description = 'Grant the admin role to an existing user';

    public function handle(AuditLogger $audit): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if (! $user->hasVerifiedEmail()) {
            $this->error('Verify the email address before granting admin access.');

            return self::FAILURE;
        }

        if ($user->hasRole(Role::Admin->value)) {
            $this->info("{$email} is already an admin.");

            return self::SUCCESS;
        }

        $user->assignRole(Role::Admin->value);
        $audit->record('user.promoted_admin', $user, after: ['role' => Role::Admin->value, 'via' => 'console']);
        $this->info("{$email} is now an admin.");

        return self::SUCCESS;
    }
}
