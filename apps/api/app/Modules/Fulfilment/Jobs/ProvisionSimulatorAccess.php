<?php

namespace App\Modules\Fulfilment\Jobs;

use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Fulfilment\Notifications\SimulatorAccessReady;
use App\Modules\Fulfilment\Simulator\SimulatorClient;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Creates the learner's GST simulator login. Retried a few times; then staff retry from the queue. */
class ProvisionSimulatorAccess implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 1800];

    public function __construct(public readonly FulfilmentTask $task) {}

    public function uniqueId(): string
    {
        return (string) $this->task->id;
    }

    public function handle(SimulatorClient $simulator, AuditLogger $audit): void
    {
        $task = $this->task->fresh() ?? $this->task;
        if ($task->status === TaskStatus::Done || $task->status === TaskStatus::Cancelled) {
            return;
        }
        if (! $simulator->configured()) {
            $task->forceFill(['status' => TaskStatus::Pending, 'last_error' => 'Simulator integration not configured yet.'])->save();

            return;
        }

        $task->increment('attempts');
        $login = $simulator->createGstUser($task->user->name);
        $task->forceFill([
            'status' => TaskStatus::Done,
            'data' => ['username' => $login['username'], 'password' => $login['password'], 'gstin' => $login['gstin'], 'simulator_user_id' => $login['id']],
            'last_error' => null,
            'completed_at' => now(),
        ])->save();
        $audit->record('fulfilment.simulator_provisioned', $task, null, ['username' => $login['username']]);
        $task->user->notify(new SimulatorAccessReady($task));
    }

    public function failed(Throwable $e): void
    {
        $this->task->forceFill(['status' => TaskStatus::Failed, 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
    }
}
