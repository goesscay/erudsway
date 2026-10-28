<?php

namespace App\Modules\Support\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Http\Requests\ReplyRequest;
use App\Modules\Support\Http\TicketPresenter;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Services\SupportDesk;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The support queue. Requires `handle_support`. */
class SupportQueueController extends Controller
{
    /** Tickets, most recently active first; filter by status, topic, assignee or a search. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['active', ...array_column(TicketStatus::cases(), 'value')])],
            'category' => ['nullable', Rule::enum(TicketCategory::class)],
            'assigned' => ['nullable', Rule::in(['me', 'unassigned'])],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        /** @var User $staff */
        $staff = $request->user();
        $page = SupportTicket::query()->with(TicketPresenter::with())
            ->when($data['status'] ?? null, fn ($q, $s) => $s === 'active'
                ? $q->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value])
                : $q->where('status', $s))
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when(($data['assigned'] ?? null) === 'me', fn ($q) => $q->where('assigned_to', $staff->id))
            ->when(($data['assigned'] ?? null) === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when($data['q'] ?? null, function ($q, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(subject) like ?', [$like])
                    ->orWhereRaw('lower(number) like ?', [$like])
                    ->orWhereHas('user', fn ($u) => $u->whereRaw('lower(email) like ?', [$like])->orWhereRaw('lower(name) like ?', [$like])));
            })
            ->orderByDesc('last_activity_at')
            ->paginate(25);

        $counts = SupportTicket::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return ApiResponse::data([
            /** @var list<array{id: string, number: string, subject: string, category: string, category_label: string, status: string, status_label: string, course: array{id: int, title: string}|null, module: string|null, order_reference: string|null, learner: array{name: string, email: string}, assignee: array{id: int, name: string}|null, last_activity_at: string|null, created_at: string}> */
            'items' => collect($page->items())->map(fn (SupportTicket $t) => TicketPresenter::summary($t))->values()->all(),
            /** @var array<string, int> */
            'counts' => $counts->map(fn ($n) => (int) $n)->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    /** Statuses, topics and the staff a ticket can be assigned to. */
    public function options(): JsonResponse
    {
        $staff = User::permission(Permission::HandleSupport->value)->orderBy('name')->get(['id', 'name']);
        $admins = User::role('admin')->orderBy('name')->get(['id', 'name']);

        return ApiResponse::data([
            /** @var list<array{value: string, label: string}> */
            'statuses' => array_map(fn (TicketStatus $s) => ['value' => $s->value, 'label' => $s->label()], TicketStatus::cases()),
            /** @var list<array{value: string, label: string}> */
            'categories' => array_map(fn (TicketCategory $c) => ['value' => $c->value, 'label' => $c->label()], TicketCategory::cases()),
            /** @var list<array{id: int, name: string}> */
            'staff' => $staff->merge($admins)->unique('id')->sortBy('name')->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
        ]);
    }

    /** A ticket with its whole conversation, internal notes included. */
    public function show(SupportTicket $ticket): JsonResponse
    {
        $ticket->load([...TicketPresenter::with(), 'messages.author:id,name', 'messages.attachments']);

        return ApiResponse::data([
            ...TicketPresenter::summary($ticket),
            /** @var list<array{id: int, body: string, from_staff: bool, internal: bool, author: string, created_at: string, attachments: list<array{id: string, name: string, mime: string, size: int}>}> */
            'messages' => TicketPresenter::messages($ticket, includeInternal: true),
        ]);
    }

    /** Change the status or the assignee. */
    public function update(Request $request, SupportTicket $ticket, SupportDesk $desk): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);
        /** @var User $staff */
        $staff = $request->user();
        if (array_key_exists('assigned_to', $data)) {
            $desk->assign($ticket, $data['assigned_to'] ? User::query()->findOrFail($data['assigned_to']) : null, $staff);
        }
        if (isset($data['status'])) {
            $desk->moveTo($ticket, TicketStatus::from($data['status']), $staff);
        }

        return $this->show($ticket);
    }

    /** Reply to the learner (emailed to them), or add an internal note with `internal`. */
    public function reply(ReplyRequest $request, SupportTicket $ticket, SupportDesk $desk): JsonResponse
    {
        $extra = $request->validate([
            'internal' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', Rule::enum(TicketStatus::class)],
        ]);
        /** @var User $staff */
        $staff = $request->user();
        $desk->staffReply(
            $ticket,
            $staff,
            (string) $request->validated('message'),
            (bool) ($extra['internal'] ?? false),
            isset($extra['status']) ? TicketStatus::from($extra['status']) : null,
            $request->uploads(),
        );

        return $this->show($ticket->refresh());
    }
}
