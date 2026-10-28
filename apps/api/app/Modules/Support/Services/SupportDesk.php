<?php

namespace App\Modules\Support\Services;

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Support\Enums\TicketCategory;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketAttachment;
use App\Modules\Support\Models\SupportTicketMessage;
use App\Modules\Support\Notifications\SupportTicketUpdated;
use App\Modules\Support\Notifications\TicketAwaitsSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Opens tickets, adds replies and notes, and moves tickets through their statuses. */
class SupportDesk
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{category: string, subject: string, message: string, course_id?: int|null, module?: string|null, order_reference?: string|null}  $data
     * @param  list<UploadedFile>  $files
     */
    public function open(User $learner, array $data, array $files = []): SupportTicket
    {
        $ticket = DB::transaction(function () use ($learner, $data, $files) {
            $ticket = SupportTicket::query()->create([
                'user_id' => $learner->id,
                'category' => TicketCategory::from($data['category']),
                'course_id' => $data['course_id'] ?? null,
                'module' => self::clean($data['module'] ?? null),
                'order_reference' => self::clean($data['order_reference'] ?? null),
                'subject' => trim($data['subject']),
                'status' => TicketStatus::Open,
                'last_activity_at' => now(),
            ]);
            $ticket->forceFill(['number' => 'SUP-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)])->save();
            $this->addMessage($ticket, $learner, $data['message'], $files, fromStaff: false, internal: false);

            return $ticket;
        });

        $this->notifySupport($ticket, $ticket->messages()->latest('id')->firstOrFail(), isNew: true);

        return $ticket;
    }

    /**
     * The learner answers. A ticket that was waiting for them, or resolved, goes back to the team.
     *
     * @param  list<UploadedFile>  $files
     */
    public function learnerReply(SupportTicket $ticket, User $learner, string $body, array $files = []): SupportTicketMessage
    {
        if ($ticket->status === TicketStatus::Closed) {
            throw ValidationException::withMessages(['message' => 'This ticket is closed. Please raise a new ticket if you still need help.']);
        }

        $message = DB::transaction(function () use ($ticket, $learner, $body, $files) {
            $message = $this->addMessage($ticket, $learner, $body, $files, fromStaff: false, internal: false);
            if (in_array($ticket->status, [TicketStatus::WaitingLearner, TicketStatus::Resolved], true)) {
                $ticket->forceFill(['status' => TicketStatus::Open, 'resolved_at' => null])->save();
            }

            return $message;
        });

        $this->notifySupport($ticket, $message, isNew: false);

        return $message;
    }

    /**
     * A staff reply (emailed to the learner) or an internal note (never shown to them). A reply
     * leaves the ticket waiting for the learner unless the staff member picks another status.
     *
     * @param  list<UploadedFile>  $files
     */
    public function staffReply(SupportTicket $ticket, User $staff, string $body, bool $internal, ?TicketStatus $status = null, array $files = []): SupportTicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $staff, $body, $internal, $status, $files) {
            $message = $this->addMessage($ticket, $staff, $body, $files, fromStaff: true, internal: $internal);
            $next = $status ?? ($internal ? null : TicketStatus::WaitingLearner);
            if ($next !== null && $next !== $ticket->status) {
                $this->moveTo($ticket, $next, $staff);
            }

            return $message;
        });

        if (! $internal) {
            $ticket->user->notify(new SupportTicketUpdated($ticket, $message));
        }

        return $message;
    }

    public function moveTo(SupportTicket $ticket, TicketStatus $status, User $by): void
    {
        $before = $ticket->status;
        if ($before === $status) {
            return;
        }
        $ticket->forceFill([
            'status' => $status,
            'resolved_at' => $status === TicketStatus::Resolved ? now() : ($status === TicketStatus::Closed ? $ticket->resolved_at : null),
            'closed_at' => $status === TicketStatus::Closed ? now() : null,
            'last_activity_at' => now(),
        ])->save();
        $this->audit->record('support_ticket.status', $ticket, ['status' => $before->value], ['status' => $status->value], $by);
    }

    public function assign(SupportTicket $ticket, ?User $assignee, User $by): void
    {
        if ($assignee !== null && ! $assignee->can(Permission::HandleSupport->value)) {
            throw ValidationException::withMessages(['assigned_to' => 'That person cannot handle support tickets.']);
        }
        $before = $ticket->assigned_to;
        $ticket->forceFill(['assigned_to' => $assignee?->id])->save();
        $this->audit->record('support_ticket.assigned', $ticket, ['assigned_to' => $before], ['assigned_to' => $assignee?->id], $by);
    }

    /** @param  list<UploadedFile>  $files */
    private function addMessage(SupportTicket $ticket, User $author, string $body, array $files, bool $fromStaff, bool $internal): SupportTicketMessage
    {
        $message = $ticket->messages()->create([
            'author_id' => $author->id,
            'from_staff' => $fromStaff,
            'internal' => $internal,
            'body' => trim($body),
        ]);
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $path = $file->storeAs("support/{$ticket->id}", Str::random(32).'.'.$ext, 'local');
            SupportTicketAttachment::query()->create([
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'uploaded_by' => $author->id,
            ]);
        }
        $ticket->forceFill(['last_activity_at' => now()])->save();

        return $message;
    }

    private function notifySupport(SupportTicket $ticket, SupportTicketMessage $message, bool $isNew): void
    {
        $inbox = (string) config('app.support_email');
        if ($inbox !== '') {
            Notification::route('mail', $inbox)->notify(new TicketAwaitsSupport($ticket, $message, $isNew));
        }
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
