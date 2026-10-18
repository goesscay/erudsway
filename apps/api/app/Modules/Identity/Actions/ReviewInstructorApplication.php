<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\ApplicationStatus;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\InstructorApplication;
use App\Modules\Identity\Models\InstructorProfile;
use App\Modules\Identity\Notifications\InstructorApplicationReviewed;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReviewInstructorApplication
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(InstructorApplication $application, User $reviewer, ?string $notes): InstructorApplication
    {
        $application = DB::transaction(function () use ($application, $reviewer, $notes) {
            $application = $this->lockPending($application);
            $this->assertNotOwn($application, $reviewer);
            $applicant = $application->user;

            $application->forceFill([
                'status' => ApplicationStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            $applicant->assignRole(Role::Instructor->value);

            InstructorProfile::query()->firstOrCreate(['user_id' => $applicant->id], [
                'slug' => $this->uniqueSlug($applicant->name),
                'display_name' => $applicant->name,
                'headline' => $application->headline,
                'approved_at' => now(),
            ]);

            $this->audit->record('instructor_application.approved', $application,
                before: ['status' => ApplicationStatus::Pending->value],
                after: ['status' => ApplicationStatus::Approved->value, 'user' => $applicant->uuid],
                actor: $reviewer,
            );

            return $application;
        });

        $application->user->notify(new InstructorApplicationReviewed($application));

        return $application;
    }

    public function reject(InstructorApplication $application, User $reviewer, string $notes): InstructorApplication
    {
        $application = DB::transaction(function () use ($application, $reviewer, $notes) {
            $application = $this->lockPending($application);
            $this->assertNotOwn($application, $reviewer);

            $application->forceFill([
                'status' => ApplicationStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            $this->audit->record('instructor_application.rejected', $application,
                before: ['status' => ApplicationStatus::Pending->value],
                after: ['status' => ApplicationStatus::Rejected->value, 'notes' => $notes],
                actor: $reviewer,
            );

            return $application;
        });

        $application->user->notify(new InstructorApplicationReviewed($application));

        return $application;
    }

    private function lockPending(InstructorApplication $application): InstructorApplication
    {
        /** @var InstructorApplication $locked */
        $locked = InstructorApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();

        if (! $locked->isPending()) {
            throw new DomainException('APPLICATION_ALREADY_REVIEWED', 'This application has already been reviewed.', 409);
        }

        return $locked;
    }

    private function assertNotOwn(InstructorApplication $application, User $reviewer): void
    {
        if ($application->user_id === $reviewer->id) {
            throw new DomainException('SELF_REVIEW', 'Another staff member must review your own application.', 403);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'instructor';
        $slug = $base;
        $n = 2;
        while (InstructorProfile::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
