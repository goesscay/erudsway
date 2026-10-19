<?php

namespace App\Modules\Learning\Http\Resources;

use App\Modules\Learning\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Note */
class NoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lecture_id' => $this->lecture_id,
            'lecture_title' => $this->lecture->title,
            'at_seconds' => $this->at_seconds,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
