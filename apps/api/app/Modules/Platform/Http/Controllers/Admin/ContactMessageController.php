<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Platform\Models\ContactMessage;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Messages from the Contact page. Requires `manage_users` (the people who look after learners). */
class ContactMessageController extends Controller
{
    /** Newest first; filter by new or handled. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'handled'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $page = ContactMessage::query()->with('handler:id,name')
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(25);

        return ApiResponse::data([
            /** @var list<array{id: string, name: string, email: string, phone: string|null, topic: string, topic_label: string, message: string, status: string, handled_by: string|null, handled_at: string|null, created_at: string}> */
            'items' => collect($page->items())->map(fn (ContactMessage $m) => $this->present($m))->values()->all(),
            'new_count' => ContactMessage::query()->where('status', 'new')->count(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    /** Mark a message handled (or new again). */
    public function update(Request $request, ContactMessage $message, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'handled'])]]);
        /** @var User $staff */
        $staff = $request->user();
        $before = $message->status;
        $handled = $data['status'] === 'handled';
        $message->forceFill([
            'status' => $data['status'],
            'handled_by' => $handled ? $staff->id : null,
            'handled_at' => $handled ? now() : null,
        ])->save();
        $audit->record('contact_message.'.($handled ? 'handled' : 'reopened'), $message, ['status' => $before], ['status' => $data['status']], $staff);

        return ApiResponse::data($this->present($message->load('handler:id,name')));
    }

    /**
     * @return array{id: string, name: string, email: string, phone: string|null, topic: string, topic_label: string, message: string, status: string, handled_by: string|null, handled_at: string|null, created_at: string}
     */
    private function present(ContactMessage $m): array
    {
        return [
            'id' => $m->uuid,
            'name' => $m->name,
            'email' => $m->email,
            'phone' => $m->phone,
            'topic' => $m->topic->value,
            'topic_label' => $m->topic->label(),
            'message' => $m->message,
            'status' => $m->status,
            'handled_by' => $m->handler?->name,
            'handled_at' => $m->handled_at?->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }
}
