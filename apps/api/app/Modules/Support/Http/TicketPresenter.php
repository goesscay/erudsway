<?php

namespace App\Modules\Support\Http;

use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketAttachment;
use App\Modules\Support\Models\SupportTicketMessage;

/** The JSON shapes shared by the learner's and the staff's ticket screens. */
final class TicketPresenter
{
    /**
     * @return array{id: string, number: string, subject: string, category: string, category_label: string, status: string, status_label: string, course: array{id: int, title: string}|null, module: string|null, order_reference: string|null, learner: array{name: string, email: string}, assignee: array{id: int, name: string}|null, last_activity_at: string|null, created_at: string}
     */
    public static function summary(SupportTicket $t, bool $forLearner = false): array
    {
        return [
            'id' => $t->uuid,
            'number' => (string) $t->number,
            'subject' => $t->subject,
            'category' => $t->category->value,
            'category_label' => $t->category->label(),
            'status' => $t->status->value,
            'status_label' => $forLearner ? $t->status->learnerLabel() : $t->status->label(),
            'course' => $t->course ? ['id' => $t->course->id, 'title' => $t->course->title] : null,
            'module' => $t->module,
            'order_reference' => $t->order_reference,
            'learner' => ['name' => $t->user->name, 'email' => $t->user->email],
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'last_activity_at' => $t->last_activity_at?->toIso8601String(),
            'created_at' => $t->created_at->toIso8601String(),
        ];
    }

    /**
     * @return list<array{id: int, body: string, from_staff: bool, internal: bool, author: string, created_at: string, attachments: list<array{id: string, name: string, mime: string, size: int}>}>
     */
    public static function messages(SupportTicket $t, bool $includeInternal): array
    {
        return $t->messages
            ->filter(fn (SupportTicketMessage $m) => $includeInternal || ! $m->internal)
            ->sortBy('id')
            ->map(fn (SupportTicketMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'from_staff' => $m->from_staff,
                'internal' => $m->internal,
                // Learners see "Erudsway Support" rather than an individual's name.
                'author' => $m->from_staff ? ($includeInternal ? ($m->author->name ?? 'Support') : 'Erudsway Support') : ($m->author->name ?? 'Learner'),
                'created_at' => $m->created_at->toIso8601String(),
                'attachments' => $m->attachments->map(fn (SupportTicketAttachment $a) => [
                    'id' => $a->uuid,
                    'name' => $a->original_name,
                    'mime' => $a->mime,
                    'size' => $a->size,
                ])->values()->all(),
            ])->values()->all();
    }

    /** @return list<string> */
    public static function with(): array
    {
        return ['user:id,name,email', 'assignee:id,name', 'course:id,title'];
    }
}
