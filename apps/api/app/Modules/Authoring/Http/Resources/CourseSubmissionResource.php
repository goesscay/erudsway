<?php

namespace App\Modules\Authoring\Http\Resources;

use App\Modules\Authoring\Models\CourseSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CourseSubmission
 */
class CourseSubmissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $course = $this->course;

        return [
            'id' => $this->id,
            /** @var 'pending'|'approved'|'rejected' */
            'status' => $this->status,
            'feedback' => $this->feedback,
            'submitted_at' => $this->created_at->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
            'course' => [
                'id' => $course->uuid,
                'slug' => $course->slug,
                'title' => $course->title,
                'status' => $course->status->value,
                'thumbnail_url' => $course->thumbnailUrl(),
                'lecture_count' => $course->lecture_count,
                'duration_seconds' => $course->duration_seconds,
                'category' => $course->category->name,
            ],
            'instructor' => [
                'name' => $this->submitter->name,
                'email' => $this->submitter->email,
            ],
            'reviewer' => $this->reviewer ? ['name' => $this->reviewer->name] : null,
        ];
    }
}
