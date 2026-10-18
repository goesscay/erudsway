<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\ApplicationStatus;
use App\Modules\Identity\Models\InstructorApplication;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;

class SubmitInstructorApplication
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): InstructorApplication
    {
        if (! $user->hasVerifiedEmail()) {
            throw new DomainException('EMAIL_NOT_VERIFIED', 'Verify your email address before applying to teach.', 403);
        }

        if ($user->isInstructor()) {
            throw new DomainException('ALREADY_INSTRUCTOR', 'You are already an approved instructor.', 409);
        }

        return DB::transaction(function () use ($user, $data) {
            // Lock the user row so two simultaneous submissions cannot both pass the pending check.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $pending = $user->instructorApplications()->where('status', ApplicationStatus::Pending->value)->exists();
            if ($pending) {
                throw new DomainException('APPLICATION_PENDING', 'Your application is already being reviewed.', 409);
            }

            return $user->instructorApplications()->create($data);
        });
    }
}
