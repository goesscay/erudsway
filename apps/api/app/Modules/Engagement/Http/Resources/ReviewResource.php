<?php

namespace App\Modules\Engagement\Http\Resources;

use App\Modules\Engagement\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A review as shown publicly. Reviewers appear by first name and initial only.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $parts = preg_split('/\s+/', trim($this->user->name)) ?: [''];
        $display = $parts[0].(count($parts) > 1 ? ' '.mb_substr((string) end($parts), 0, 1).'.' : '');

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'body' => $this->body,
            'author' => $display,
            /** @var 'visible'|'flagged'|'hidden' */
            'status' => $this->status->value,
            'instructor_reply' => $this->instructor_reply,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'report_count' => $this->report_count,
            /** Report reasons, for moderators. */
            'reports' => $this->whenLoaded('reports', fn () => $this->reports->pluck('reason')->values()->all()),
            'course' => $this->whenLoaded('course', fn () => ['slug' => $this->course->slug, 'title' => $this->course->title]),
        ];
    }
}
