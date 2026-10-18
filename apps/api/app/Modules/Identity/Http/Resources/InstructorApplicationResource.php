<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\InstructorApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InstructorApplication
 */
class InstructorApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'status' => $this->status->value,
            'headline' => $this->headline,
            'topics' => $this->topics,
            'experience' => $this->experience,
            'teaching_experience' => $this->teaching_experience->value,
            'sample_url' => $this->sample_url,
            'review_notes' => $this->review_notes,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'submitted_at' => $this->created_at->toIso8601String(),
            'applicant' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->uuid,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->uuid,
                'name' => $this->reviewer->name,
            ] : null),
        ];
    }
}
