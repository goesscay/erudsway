<?php

namespace App\Modules\Fulfilment\Listeners;

use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Jobs\ProvisionSimulatorAccess;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Learning\Events\EditionGranted;

/**
 * Opens a task for each extra the new edition brings. One per learner and course: buying
 * again after the simulator login was removed sets it up afresh, and a renewal or upgrade
 * moves the login's end date with the access.
 */
class CreateFulfilmentTasks
{
    public function handle(EditionGranted $event): void
    {
        $enrollment = $event->enrollment;
        $edition = $enrollment->edition;
        if ($edition === null) {
            return;
        }
        $base = ['user_id' => $enrollment->user_id, 'course_id' => $enrollment->course_id];

        if ($edition->grants_simulator) {
            $task = FulfilmentTask::query()->firstOrCreate(
                [...$base, 'type' => TaskType::SimulatorAccess],
                ['status' => TaskStatus::Pending, 'enrollment_id' => $enrollment->id, 'edition_id' => $edition->id],
            );
            $fresh = $task->wasRecentlyCreated || $task->status === TaskStatus::Failed || $task->removed_at !== null;
            $task->forceFill([
                'enrollment_id' => $enrollment->id,
                'edition_id' => $edition->id,
                'access_ends_at' => $enrollment->expires_at,
                // A later end date earns a new reminder.
                'reminded_at' => $task->access_ends_at?->eq($enrollment->expires_at) ? $task->reminded_at : null,
                ...($task->removed_at !== null ? ['status' => TaskStatus::Pending, 'data' => null, 'removed_at' => null, 'completed_at' => null] : []),
            ])->save();
            if ($fresh) {
                ProvisionSimulatorAccess::dispatch($task);
            }
        }

        if ($edition->includes_doubt_session && ! $event->previous?->includes_doubt_session) {
            FulfilmentTask::query()->firstOrCreate(
                [...$base, 'type' => TaskType::DoubtSession],
                ['status' => TaskStatus::AwaitingLearner, 'enrollment_id' => $enrollment->id, 'edition_id' => $edition->id],
            );
        }
    }
}
