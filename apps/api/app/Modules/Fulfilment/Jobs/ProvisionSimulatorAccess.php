<?php

namespace App\Modules\Fulfilment\Jobs;

use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Fulfilment\Notifications\SimulatorAccessReady;
use App\Modules\Fulfilment\Notifications\SimulatorTrialReady;
use App\Modules\Fulfilment\Simulator\SimulatorClient;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Creates the learner's GST simulator login, for a paid edition or a free trial. A paid login
 * takes over a running trial's login, so the learner keeps their practice work. Retried a few
 * times; then staff retry from the queue.
 */
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

        $trial = $task->type === TaskType::SimulatorTrial;
        $adopted = $trial ? null : $this->runningTrial($task);
        if ($adopted !== null) {
            $data = $adopted->data ?? [];
            $adopted->forceFill(['status' => TaskStatus::Cancelled, 'staff_notes' => 'Login moved to paid access.'])->save();
        } else {
            $task->increment('attempts');
            $login = $simulator->createGstUser($task->user->name);
            $data = ['username' => $login['username'], 'password' => $login['password'], 'gstin' => $login['gstin'], 'simulator_user_id' => $login['id']];
        }

        $task->forceFill([
            'status' => TaskStatus::Done,
            'data' => $data,
            'last_error' => null,
            'completed_at' => now(),
            'access_ends_at' => $trial
                ? now()->addDays(max(1, (int) config('services.simulator.trial_days', 7)))
                : $this->paidEndsAt($task),
            'reminded_at' => null,
            'removed_at' => null,
        ])->save();
        $audit->record('fulfilment.simulator_provisioned', $task, null, ['username' => $data['username'] ?? null, 'type' => $task->type->value, 'from_trial' => $adopted !== null]);
        $task->user->notify($trial ? new SimulatorTrialReady($task) : new SimulatorAccessReady($task));
    }

    public function failed(Throwable $e): void
    {
        $this->task->forceFill(['status' => TaskStatus::Failed, 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();
    }

    private function runningTrial(FulfilmentTask $task): ?FulfilmentTask
    {
        return FulfilmentTask::query()
            ->where('user_id', $task->user_id)->where('type', TaskType::SimulatorTrial)
            ->where('status', TaskStatus::Done)->whereNull('removed_at')
            ->first();
    }

    /** The end of the paid access: the enrollment's expiry (null when it never ends). */
    private function paidEndsAt(FulfilmentTask $task): ?Carbon
    {
        return Enrollment::query()->active()->where('user_id', $task->user_id)->where('course_id', $task->course_id)->first()?->expires_at;
    }
}
