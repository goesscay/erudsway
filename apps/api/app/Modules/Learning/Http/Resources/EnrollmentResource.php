<?php

namespace App\Modules\Learning\Http\Resources;

use App\Modules\Learning\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A course in the learner's library (My Learning), or in an admin's view of a user.
 *
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $course = $this->course;

        return [
            'id' => $this->id,
            /** @var 'active'|'refunded'|'revoked' */
            'status' => $this->status->value,
            /** @var 'purchase'|'free'|'coupon'|'admin_grant' */
            'source' => $this->source->value,
            'progress_percent' => $this->progress_percent,
            'completed_count' => $this->completed_count,
            'last_lecture_id' => $this->last_lecture_id,
            'granted_at' => $this->granted_at->toIso8601String(),
            'last_accessed_at' => $this->last_accessed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'reason' => $this->reason,
            'course' => [
                'id' => $course->uuid,
                'slug' => $course->slug,
                'title' => $course->title,
                'thumbnail_url' => $course->thumbnailUrl(),
                'instructor_name' => $course->instructorProfile->display_name ?? $course->instructor->name,
                'lecture_count' => $course->lecture_count,
                'duration_seconds' => $course->duration_seconds,
            ],
        ];
    }
}
