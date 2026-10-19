<?php

namespace App\Modules\Platform\Http\Resources;

use App\Modules\Platform\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor' => $this->actor ? ['name' => $this->actor->name, 'email' => $this->actor->email] : null,
            /** Short model name, e.g. Refund. */
            'subject_type' => $this->subject_type ? class_basename($this->subject_type) : null,
            'subject_id' => $this->subject_id,
            'before' => $this->before,
            'after' => $this->after,
            'ip' => $this->ip,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
