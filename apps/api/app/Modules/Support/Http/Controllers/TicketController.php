<?php

namespace App\Modules\Support\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Http\Requests\ReplyRequest;
use App\Modules\Support\Http\Requests\StoreTicketRequest;
use App\Modules\Support\Http\TicketPresenter;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketAttachment;
use App\Modules\Support\Services\SupportDesk;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The learner's side of the Help Centre: raise a ticket, follow it, reply and close it. */
class TicketController extends Controller
{
    /** Topics and the learner's own courses, for the "Raise a ticket" form. */
    public function options(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $courses = Enrollment::query()->where('user_id', $user->id)->with('course:id,title')->latest('id')->get()
            ->map(fn (Enrollment $e) => $e->course)->filter()->unique('id')
            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])->values()->all();

        return ApiResponse::data([
            /** @var list<array{value: 'account'|'courses'|'gst_simulator'|'tds_simulator'|'payments'|'certificates'|'faculty'|'technical', label: string}> */
            'categories' => array_map(fn (TicketCategory $c) => ['value' => $c->value, 'label' => $c->label()], TicketCategory::cases()),
            /** @var list<array{id: int, title: string}> */
            'courses' => $courses,
        ]);
    }

    /** The learner's tickets, most recently active first. */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tickets = SupportTicket::query()->where('user_id', $user->id)->with(TicketPresenter::with())
            ->orderByDesc('last_activity_at')->limit(100)->get();

        return ApiResponse::data([
            /** @var list<array{id: string, number: string, subject: string, category: string, category_label: string, status: string, status_label: string, course: array{id: int, title: string}|null, module: string|null, order_reference: string|null, learner: array{name: string, email: string}, assignee: array{id: int, name: string}|null, last_activity_at: string|null, created_at: string}> */
            'items' => $tickets->map(fn (SupportTicket $t) => TicketPresenter::summary($t, forLearner: true))->values()->all(),
        ]);
    }

    /** Raise a ticket. The support team is emailed straight away. */
    public function store(StoreTicketRequest $request, SupportDesk $desk): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{category: string, subject: string, message: string, course_id?: int|null, module?: string|null, order_reference?: string|null} $data */
        $data = $request->safe()->except('attachments');
        $ticket = $desk->open($user, $data, $request->uploads());

        return ApiResponse::data(['id' => $ticket->uuid, 'number' => (string) $ticket->number], status: 201);
    }

    /** One of the learner's tickets with its conversation (internal notes left out). */
    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->ensureOwner($request, $ticket);
        $ticket->load([...TicketPresenter::with(), 'messages.author:id,name', 'messages.attachments']);

        return ApiResponse::data([
            ...TicketPresenter::summary($ticket, forLearner: true),
            /** @var list<array{id: int, body: string, from_staff: bool, internal: bool, author: string, created_at: string, attachments: list<array{id: string, name: string, mime: string, size: int}>}> */
            'messages' => TicketPresenter::messages($ticket, includeInternal: false),
        ]);
    }

    /** Reply on the ticket. A resolved ticket reopens. */
    public function reply(ReplyRequest $request, SupportTicket $ticket, SupportDesk $desk): JsonResponse
    {
        /** @var User $user */
        $user = $this->ensureOwner($request, $ticket);
        $desk->learnerReply($ticket, $user, (string) $request->validated('message'), $request->uploads());

        return ApiResponse::data(['status' => $ticket->status->value], status: 201);
    }

    /** Close the ticket when the problem is sorted. */
    public function close(Request $request, SupportTicket $ticket, SupportDesk $desk): JsonResponse
    {
        $user = $this->ensureOwner($request, $ticket);
        $desk->moveTo($ticket, TicketStatus::Closed, $user);

        return ApiResponse::data(['status' => $ticket->status->value]);
    }

    /** Download an attachment. Only the ticket's owner and support staff may. */
    public function attachment(Request $request, SupportTicketAttachment $attachment): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attachment->loadMissing('ticket', 'message');
        $staff = $user->can(Permission::HandleSupport->value);
        abort_unless($staff || ($attachment->ticket->user_id === $user->id && ! $attachment->message?->internal), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    private function ensureOwner(Request $request, SupportTicket $ticket): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($ticket->user_id === $user->id, 404);

        return $user;
    }
}
