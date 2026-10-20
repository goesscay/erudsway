<?php

namespace App\Modules\Fulfilment\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Http\Resources\FulfilmentTaskResource;
use App\Modules\Fulfilment\Jobs\ProvisionSimulatorAccess;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Fulfilment\Notifications\DoubtSessionScheduled;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** The queue of promised extras. Requires `manage_fulfilment`. */
class FulfilmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Open tasks first (needing action at the top), newest last. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'done', 'all'])],
            'type' => ['sometimes', Rule::enum(TaskType::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $status = $data['status'] ?? 'open';
        $open = [TaskStatus::Pending, TaskStatus::AwaitingLearner, TaskStatus::Requested, TaskStatus::Scheduled, TaskStatus::Failed];

        $tasks = FulfilmentTask::query()
            ->with(['user', 'course', 'edition', 'handler'])
            ->when($status === 'open', fn (Builder $q) => $q->whereIn('status', array_map(fn ($s) => $s->value, $open)))
            ->when($status === 'done', fn (Builder $q) => $q->whereIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value]))
            ->when($data['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->orderByRaw("case status when 'failed' then 0 when 'requested' then 1 when 'pending' then 2 when 'scheduled' then 3 when 'awaiting_learner' then 4 else 5 end")
            ->orderBy('created_at')
            ->paginate(25);

        return FulfilmentTaskResource::collection($tasks);
    }

    /** Try creating the simulator login again (after the integration is configured, or a failure). */
    public function retry(Request $request, FulfilmentTask $task): FulfilmentTaskResource
    {
        if ($task->type !== TaskType::SimulatorAccess || $task->status === TaskStatus::Done) {
            throw new DomainException('NOT_RETRYABLE', 'Only a simulator login that isn’t ready yet can be retried.', 422);
        }
        $task->forceFill(['status' => TaskStatus::Pending, 'last_error' => null])->save();
        ProvisionSimulatorAccess::dispatch($task);
        $this->audit->record('fulfilment.retried', $task, null, null, $this->user($request));

        return $this->present($task);
    }

    /** Schedule, complete or cancel a task, with notes the learner sees once scheduled. */
    public function update(Request $request, FulfilmentTask $task): FulfilmentTaskResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'done', 'cancelled'])],
            'scheduled_for' => ['required_if:status,scheduled', 'nullable', 'date', 'after:now'],
            'staff_notes' => ['nullable', 'string', 'max:2000'],
        ], ['scheduled_for.required_if' => 'Pick the date and time of the session.']);
        if ($data['status'] === 'scheduled' && $task->type !== TaskType::DoubtSession) {
            throw new DomainException('NOT_SCHEDULABLE', 'Only doubt sessions are scheduled.', 422);
        }

        $before = $task->status->value;
        $status = TaskStatus::from($data['status']);
        $task->forceFill([
            'status' => $status,
            'scheduled_for' => $data['scheduled_for'] ?? $task->scheduled_for,
            'staff_notes' => $data['staff_notes'] ?? $task->staff_notes,
            'handled_by' => $this->user($request)->id,
            'completed_at' => $status === TaskStatus::Done ? now() : $task->completed_at,
        ])->save();
        $this->audit->record('fulfilment.updated', $task, ['status' => $before], ['status' => $status->value], $this->user($request));

        if ($status === TaskStatus::Scheduled) {
            $task->user->notify(new DoubtSessionScheduled($task));
        }

        return $this->present($task);
    }

    private function present(FulfilmentTask $task): FulfilmentTaskResource
    {
        return new FulfilmentTaskResource($task->refresh()->load(['user', 'course', 'edition', 'handler']));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
