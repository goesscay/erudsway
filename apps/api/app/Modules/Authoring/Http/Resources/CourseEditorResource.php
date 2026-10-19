<?php

namespace App\Modules\Authoring\Http\Resources;

use App\Modules\Authoring\Services\CourseReadiness;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseSection;
use App\Modules\Catalog\Models\Lecture;
use App\Modules\Media\Models\LectureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything the course builder needs, including drafts, processing state and the
 * readiness checklist.
 *
 * @mixin Course
 */
class CourseEditorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $submission = $this->latestSubmission;

        return [
            'id' => $this->uuid,
            'slug' => $this->slug,
            /** @var 'draft'|'in_review'|'changes_requested'|'published'|'unpublished'|'archived' */
            'status' => $this->status->value,
            'title' => $this->title,
            // Who owns it: the builder shows a notice when Erudsway staff open someone else's course.
            'instructor' => ['id' => $this->instructor->uuid, 'name' => $this->instructor->name],
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'language' => $this->language,
            'level' => $this->level->value,
            'category' => [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
                'parent' => $this->category->parent ? ['slug' => $this->category->parent->slug, 'name' => $this->category->parent->name] : null,
            ],
            'price_tier_id' => $this->price_tier_id,
            'price' => $this->priceTier ? ['amount' => $this->priceTier->amount, 'currency' => $this->priceTier->currency] : null,
            'pricing_confirmed' => $this->pricing_confirmed_at !== null,
            'thumbnail_url' => $this->thumbnailUrl(),
            'outcomes' => $this->outcomes,
            'requirements' => $this->requirements,
            'audience' => $this->audience,
            'published_at' => $this->published_at?->toIso8601String(),
            'lecture_count' => $this->lecture_count,
            'duration_seconds' => $this->duration_seconds,
            'latest_submission' => $submission ? [
                /** @var 'pending'|'approved'|'rejected' */
                'status' => $submission->status,
                'feedback' => $submission->feedback,
                'submitted_at' => $submission->created_at->toIso8601String(),
                'decided_at' => $submission->decided_at?->toIso8601String(),
            ] : null,
            /** @var list<array{key: string, group: string, label: string, ok: bool}> */
            'checklist' => app(CourseReadiness::class)->check($this->resource),
            /** @var list<array{id: int, title: string, position: int, lectures: list<array{id: int, title: string, kind: 'video'|'article'|'quiz'|'assignment', position: int, is_free_preview: bool, duration_seconds: int, article_html: string|null, video: array{id: string, status: 'uploading'|'processing'|'ready'|'failed', original_name: string, duration_seconds: int, failure_reason: string|null}|null, resources: list<array{id: int, name: string, size_bytes: int}>}>}> */
            'curriculum' => $this->sections->map(fn (CourseSection $section) => [
                'id' => $section->id,
                'title' => $section->title,
                'position' => $section->position,
                'lectures' => $section->lectures->map(fn (Lecture $lecture) => [
                    'id' => $lecture->id,
                    'title' => $lecture->title,
                    'kind' => $lecture->kind->value,
                    'position' => $lecture->position,
                    'is_free_preview' => $lecture->is_free_preview,
                    'duration_seconds' => $lecture->duration_seconds,
                    'article_html' => $lecture->article_html,
                    'video' => $lecture->videoAsset ? [
                        'id' => $lecture->videoAsset->uuid,
                        'status' => $lecture->videoAsset->status->value,
                        'original_name' => $lecture->videoAsset->original_name,
                        'duration_seconds' => $lecture->videoAsset->duration_seconds,
                        'failure_reason' => $lecture->videoAsset->failure_reason,
                    ] : null,
                    'resources' => $lecture->resources->map(fn (LectureResource $r) => [
                        'id' => $r->id,
                        'name' => $r->original_name,
                        'size_bytes' => $r->size_bytes,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public static function load(Course $course): self
    {
        return new self($course->load([
            'instructor', 'category.parent', 'priceTier', 'latestSubmission',
            'sections.lectures.videoAsset', 'sections.lectures.resources', 'lectures.videoAsset',
        ]));
    }
}
