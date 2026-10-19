<?php

namespace App\Modules\Learning\Http\Resources;

use App\Modules\Catalog\Models\Lecture;
use App\Modules\Media\Models\LectureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One lecture's readable content and files. Video is requested separately (playback).
 *
 * @mixin Lecture
 */
class LectureContentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            /** @var 'video'|'article'|'quiz'|'assignment' */
            'kind' => $this->kind->value,
            // Sanitised with HTMLPurifier when the instructor saved it.
            'article_html' => $this->kind->value === 'article' ? $this->article_html : null,
            /** @var list<array{id: int, name: string, size_bytes: int, download_url: string}> */
            'resources' => $this->resources->map(fn (LectureResource $r) => [
                'id' => $r->id,
                'name' => $r->original_name,
                'size_bytes' => $r->size_bytes,
                'download_url' => url("/api/v1/learn/resources/{$r->id}/download"),
            ])->values()->all(),
        ];
    }
}
