<?php

namespace App\Modules\Fulfilment\Http\Resources;

use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A task as staff see it in the queue. The simulator password is never included here.
 *
 * @mixin FulfilmentTask
 */
class FulfilmentTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'id' => $this->uuid,
            /** @var 'simulator_access'|'doubt_session' */
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            /** @var 'pending'|'awaiting_learner'|'requested'|'scheduled'|'done'|'failed'|'cancelled' */
            'status' => $this->status->value,
            'learner' => ['id' => $this->user->uuid, 'name' => $this->user->name, 'email' => $this->user->email],
            'course' => ['slug' => $this->course->slug, 'title' => $this->course->title],
            'edition' => $this->edition?->name,
            /** @var string|null */
            'simulator_username' => $this->type === TaskType::SimulatorAccess ? ($data['username'] ?? null) : null,
            /** @var string|null */
            'preferred_times' => $data['preferred_times'] ?? null,
            /** @var string|null */
            'questions' => $data['questions'] ?? null,
            /** @var string|null */
            'phone' => $data['phone'] ?? null,
            'staff_notes' => $this->staff_notes,
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'handled_by' => $this->handler?->name,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
