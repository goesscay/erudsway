<?php

namespace App\Modules\Fulfilment\Listeners;

use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Jobs\ProvisionSimulatorAccess;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Learning\Events\EditionGranted;

/** Opens a task for each extra the new edition brings that the old one didn't. One per learner and course. */
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

        if ($edition->grants_simulator && ! $event->previous?->grants_simulator) {
            $task = FulfilmentTask::query()->firstOrCreate(
                [...$base, 'type' => TaskType::SimulatorAccess],
                ['status' => TaskStatus::Pending, 'enrollment_id' => $enrollment->id, 'edition_id' => $edition->id],
            );
            if ($task->wasRecentlyCreated || $task->status === TaskStatus::Failed) {
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
