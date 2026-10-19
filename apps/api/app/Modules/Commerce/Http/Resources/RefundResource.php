<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Models\Refund;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Refund */
class RefundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->orderItem;
        $order = $item->order;
        $progress = Enrollment::query()->where('user_id', $order->user_id)->where('course_id', $item->course_id)->value('progress_percent');

        return [
            'id' => $this->id,
            /** @var 'requested'|'rejected'|'processing'|'processed'|'failed' */
            'status' => $this->status->value,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'auto_approved' => $this->auto_approved,
            'decision_note' => $this->decision_note,
            'created_at' => $this->created_at->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
            'order' => ['id' => $order->uuid, 'number' => $order->number, 'paid_at' => $order->paid_at?->toIso8601String()],
            'course' => ['slug' => $item->course->slug, 'title' => $item->course_title],
            'learner' => ['name' => $order->user->name, 'email' => $order->user->email],
            'progress_percent' => (int) ($progress ?? 0),
        ];
    }
}
