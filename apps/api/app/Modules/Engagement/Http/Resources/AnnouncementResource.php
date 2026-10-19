<?php

namespace App\Modules\Engagement\Http\Resources;

use App\Modules\Engagement\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Announcement */
class AnnouncementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            // Sanitised with HTMLPurifier when posted.
            'body_html' => $this->body_html,
            'author' => ['name' => $this->user->name],
            'recipients_count' => $this->recipients_count,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
