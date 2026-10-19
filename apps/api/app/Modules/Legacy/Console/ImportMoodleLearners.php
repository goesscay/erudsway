<?php

namespace App\Modules\Legacy\Console;

use App\Models\User;
use App\Modules\Identity\Enums\Role;
use App\Modules\Legacy\Moodle\Bundle;
use App\Modules\Legacy\Moodle\LearnerImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ImportMoodleLearners extends Command
{
    protected $signature = 'moodle:import-learners
        {bundle : Folder made by deploy/server/bin/moodle-export}
        {--admin= : Email of the admin the access grants are recorded against}';

    protected $description = 'Give a Moodle course’s learners accounts, access, their progress and certificates';

    public function handle(LearnerImporter $importer): int
    {
        $admin = User::query()->where('email', mb_strtolower((string) $this->option('admin')))->first();
        if ($admin === null || ! $admin->hasRole(Role::Admin->value)) {
            $this->error('Give --admin the email of an admin account.');

            return self::FAILURE;
        }

        try {
            // The audit log names whoever is signed in; on the command line that's the admin given.
            Auth::setUser($admin);
            $rows = $importer->import(Bundle::open((string) $this->argument('bundle')), $admin);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Learner', 'Account', 'Access', 'Lectures done', 'Certificate'], array_map(fn (array $r) => [
            $r['email'], $r['account'], $r['access'], $r['completed'], $r['certificate'],
        ], $rows));

        return self::SUCCESS;
    }
}
