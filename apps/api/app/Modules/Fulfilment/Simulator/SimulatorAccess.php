<?php

namespace App\Modules\Fulfilment\Simulator;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Jobs\ProvisionSimulatorAccess;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Fulfilment\Notifications\SimulatorAccessEnded;
use App\Modules\Fulfilment\Notifications\SimulatorAccessEnding;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Who has a GST simulator login and until when. A login comes from a 7-day trial the learner
 * starts themselves, or from a paid edition (a simulator plan, or a course edition that
 * includes it). When the trial or the paid access ends, the login is removed from the
 * simulator — its sessions never expire on their own, so that's the only real "end".
 */
class SimulatorAccess
{
    /** Days before the end when the learner is reminded. */
    public const REMIND_TRIAL_DAYS = 2;

    public const REMIND_PAID_DAYS = 3;

    public function __construct(
        private readonly SimulatorClient $simulator,
        private readonly AuditLogger $audit,
    ) {}

    public function plansCourse(): ?Course
    {
        return Course::query()->where('slug', (string) config('services.simulator.plans_course'))->first();
    }

    /** The login the learner can use now: paid access first, then a trial. */
    public function liveLogin(User $user): ?FulfilmentTask
    {
        return FulfilmentTask::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [TaskType::SimulatorAccess, TaskType::SimulatorTrial])
            ->where('status', TaskStatus::Done)
            ->whereNull('removed_at')
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [TaskType::SimulatorAccess->value])
            ->orderByDesc('access_ends_at')
            ->first();
    }

    /**
     * Where the learner stands, for the GST Simulator page.
     *
     * @return array{state: 'none'|'preparing'|'trial'|'paid'|'trial_ended', ends_at: string|null, trial_ends_at: string|null, can_start_trial: bool, login: array{username: string, password: string|null, gstin: string|null}|null, course_slug: string|null}
     */
    public function state(User $user): array
    {
        $tasks = FulfilmentTask::query()->with('course:id,slug')->where('user_id', $user->id)
            ->whereIn('type', [TaskType::SimulatorAccess, TaskType::SimulatorTrial])->get();
        $live = $this->liveLogin($user);
        $trial = $tasks->first(fn (FulfilmentTask $t) => $t->type === TaskType::SimulatorTrial);
        $preparing = $tasks->first(fn (FulfilmentTask $t) => in_array($t->status, [TaskStatus::Pending, TaskStatus::Failed], true) && $t->removed_at === null);

        $state = match (true) {
            $live !== null => $live->type === TaskType::SimulatorTrial ? 'trial' : 'paid',
            $preparing !== null => 'preparing',
            $trial !== null => 'trial_ended',
            default => 'none',
        };

        return [
            'state' => $state,
            'ends_at' => $live?->access_ends_at?->toIso8601String(),
            'trial_ends_at' => $trial?->access_ends_at?->toIso8601String(),
            'can_start_trial' => $trial === null && $live === null && $preparing === null,
            'login' => $live ? [
                'username' => (string) ($live->data['username'] ?? ''),
                'password' => isset($live->data['password']) ? (string) $live->data['password'] : null,
                'gstin' => isset($live->data['gstin']) ? (string) $live->data['gstin'] : null,
            ] : null,
            'course_slug' => $live !== null && $live->type === TaskType::SimulatorAccess ? $live->course->slug : null,
        ];
    }

    /** Start the learner's one free trial. The login is created in the background (usually seconds). */
    public function startTrial(User $user): FulfilmentTask
    {
        if (! $user->hasVerifiedEmail()) {
            throw new DomainException('EMAIL_UNVERIFIED', 'Verify your email address to start the free trial.', 403);
        }
        $course = $this->plansCourse();
        if ($course === null || ! $this->simulator->configured()) {
            throw new DomainException('SIMULATOR_UNAVAILABLE', 'The simulator trial isn’t available right now. Please try again later.', 503);
        }
        if ($this->liveLogin($user)?->type === TaskType::SimulatorAccess) {
            throw new DomainException('ALREADY_HAS_ACCESS', 'You already have simulator access.', 409);
        }

        $task = DB::transaction(function () use ($user, $course) {
            $existing = FulfilmentTask::query()->where('user_id', $user->id)->where('type', TaskType::SimulatorTrial)->lockForUpdate()->first();
            if ($existing !== null) {
                throw new DomainException('TRIAL_USED', 'You’ve already used your free trial. Choose a plan to keep practising.', 409);
            }

            return FulfilmentTask::query()->create([
                'type' => TaskType::SimulatorTrial,
                'status' => TaskStatus::Pending,
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);
        });
        $this->audit->record('simulator.trial_started', $task, null, ['days' => $this->trialDays()], $user);
        ProvisionSimulatorAccess::dispatch($task);

        return $task;
    }

    public function trialDays(): int
    {
        return max(1, (int) config('services.simulator.trial_days', 7));
    }

    /** Whether a paid login is still covered by an active enrollment whose edition includes the simulator. */
    public function paidStillCovered(FulfilmentTask $task): bool
    {
        return Enrollment::query()->active()
            ->where('user_id', $task->user_id)->where('course_id', $task->course_id)
            ->whereHas('edition', fn ($q) => $q->where('grants_simulator', true))
            ->exists();
    }

    /**
     * Remove logins whose trial or paid access has ended, and remind learners whose access ends
     * soon. Run hourly by the scheduler.
     *
     * @return array{removed: int, reminded: int, failed: int}
     */
    public function sweep(): array
    {
        $counts = ['removed' => 0, 'reminded' => 0, 'failed' => 0];
        if (! $this->simulator->configured()) {
            return $counts;
        }

        $live = FulfilmentTask::query()->with('user', 'course')
            ->whereIn('type', [TaskType::SimulatorAccess, TaskType::SimulatorTrial])
            ->where('status', TaskStatus::Done)->whereNull('removed_at')->get();

        foreach ($live as $task) {
            $ended = $task->type === TaskType::SimulatorTrial
                ? $task->access_ends_at !== null && $task->access_ends_at->isPast()
                : ! $this->paidStillCovered($task);

            if ($ended) {
                try {
                    $this->remove($task);
                    $counts['removed']++;
                } catch (Throwable $e) {
                    report($e);
                    $task->forceFill(['last_error' => mb_substr('Removing the login failed: '.$e->getMessage(), 0, 500)])->save();
                    $counts['failed']++;
                }

                continue;
            }

            $window = $task->type === TaskType::SimulatorTrial ? self::REMIND_TRIAL_DAYS : self::REMIND_PAID_DAYS;
            if ($task->reminded_at === null && $task->access_ends_at !== null && $task->access_ends_at->isFuture() && $task->access_ends_at->lte(now()->addDays($window))) {
                $task->forceFill(['reminded_at' => now()])->save();
                $task->user->notify(new SimulatorAccessEnding($task));
                $counts['reminded']++;
            }
        }

        return $counts;
    }

    /** Delete the login from the simulator and keep a note of it (the password is dropped). */
    public function remove(FulfilmentTask $task): void
    {
        $simulatorId = (int) ($task->data['simulator_user_id'] ?? 0);
        if ($simulatorId > 0) {
            $this->simulator->deleteGstUser($simulatorId);
        }
        $data = $task->data ?? [];
        unset($data['password']);
        $task->forceFill(['removed_at' => now(), 'data' => $data, 'last_error' => null])->save();
        $this->audit->record('fulfilment.simulator_removed', $task, null, ['username' => $data['username'] ?? null, 'type' => $task->type->value]);
        $task->user->notify(new SimulatorAccessEnded($task));
    }
}
