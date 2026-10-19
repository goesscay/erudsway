<?php

namespace App\Modules\Assessment\Http\Resources;

use App\Modules\Assessment\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AssignmentSubmission
 */
class SubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            /** @var array{name: string, size_bytes: int, url: string}|null */
            'file' => $this->file_path ? [
                'name' => (string) $this->file_name,
                'size_bytes' => (int) $this->file_size,
                'url' => url("/api/v1/assignment-submissions/{$this->id}/file"),
            ] : null,
            /** @var 'submitted'|'reviewed' */
            'status' => $this->status,
            'feedback' => $this->feedback,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'learner' => $this->whenLoaded('user', fn () => ['name' => $this->user->name]),
            'lecture' => $this->whenLoaded('assignment', fn () => $this->assignment->relationLoaded('lecture') ? [
                'id' => $this->assignment->lecture->id,
                'title' => $this->assignment->lecture->title,
                'course' => ['slug' => $this->assignment->lecture->course->slug, 'title' => $this->assignment->lecture->course->title],
            ] : null),
        ];
    }
}
