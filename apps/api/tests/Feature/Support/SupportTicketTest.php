<?php

use App\Modules\Identity\Enums\Permission;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Notifications\SupportTicketUpdated;
use App\Modules\Support\Notifications\TicketAwaitsSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/** @return array<string, mixed> */
function ticketBody(array $overrides = []): array
{
    return [
        'category' => 'gst_simulator',
        'subject' => 'Cannot open the GSTR-1 module',
        'message' => 'The simulator shows a blank page when I open GSTR-1 after logging in.',
        ...$overrides,
    ];
}

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    config(['app.support_email' => 'support@erudsway.com']);
});

it('lets a learner raise a ticket with a screenshot and emails the support inbox', function () {
    $learner = makeUser();

    $response = $this->actingAs($learner)->withHeaders(spaHeaders())->post('/api/v1/me/support/tickets', ticketBody([
        'attachments' => [UploadedFile::fake()->image('blank.png')],
    ]))->assertCreated();

    $ticket = SupportTicket::query()->sole();
    expect($response->json('data.number'))->toBe('SUP-'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT))
        ->and($ticket->status->value)->toBe('open')
        ->and($ticket->attachments()->count())->toBe(1);
    Storage::disk('local')->assertExists($ticket->attachments()->value('path'));
    Notification::assertSentTo(new AnonymousNotifiable, TicketAwaitsSupport::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'support@erudsway.com' && $n->isNew);

    $this->getJson('/api/v1/me/support/tickets')->assertOk()->assertJsonPath('data.items.0.subject', 'Cannot open the GSTR-1 module');
});

it('rejects files that are not images or PDFs, and more than three', function () {
    $this->actingAs(makeUser())->withHeaders(spaHeaders())->post('/api/v1/me/support/tickets', ticketBody([
        'attachments' => [UploadedFile::fake()->create('virus.exe', 10)],
    ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrorFor('attachments.0', 'error.fields');

    $this->post('/api/v1/me/support/tickets', ticketBody([
        'attachments' => array_map(fn ($i) => UploadedFile::fake()->image("s{$i}.png"), range(1, 4)),
    ]), ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrorFor('attachments', 'error.fields');

    expect(SupportTicket::query()->count())->toBe(0);
});

it('keeps tickets and attachments private to their owner', function () {
    $owner = makeUser();
    $this->actingAs($owner)->withHeaders(spaHeaders())->post('/api/v1/me/support/tickets', ticketBody([
        'attachments' => [UploadedFile::fake()->image('blank.png')],
    ]))->assertCreated();
    $ticket = SupportTicket::query()->sole();
    $file = $ticket->attachments()->value('uuid');

    $this->getJson("/api/v1/support/attachments/{$file}")->assertOk();

    $this->actingAs(makeUser());
    $this->getJson("/api/v1/me/support/tickets/{$ticket->uuid}")->assertNotFound();
    $this->postJson("/api/v1/me/support/tickets/{$ticket->uuid}/replies", ['message' => 'Hello there'])->assertNotFound();
    $this->getJson("/api/v1/support/attachments/{$file}")->assertNotFound();
});

it('lets support staff reply, keep internal notes and resolve, and the learner reopen by replying', function () {
    $learner = makeUser();
    $this->actingAs($learner)->withHeaders(spaHeaders())->postJson('/api/v1/me/support/tickets', ticketBody())->assertCreated();
    $ticket = SupportTicket::query()->sole();

    $agent = makeStaff(Permission::HandleSupport);
    $this->actingAs($agent);
    $this->getJson('/api/v1/admin/support/tickets?status=active')->assertOk()->assertJsonPath('data.total', 1);
    $this->postJson("/api/v1/admin/support/tickets/{$ticket->uuid}/replies", ['message' => 'Checked logs: session token expired.', 'internal' => true])
        ->assertOk()->assertJsonPath('data.status', 'open');
    Notification::assertNotSentTo($learner, SupportTicketUpdated::class);

    $this->postJson("/api/v1/admin/support/tickets/{$ticket->uuid}/replies", ['message' => 'Please sign out and in again, then retry.', 'status' => 'resolved'])
        ->assertOk()->assertJsonPath('data.status', 'resolved');
    Notification::assertSentTo($learner, SupportTicketUpdated::class);
    $this->patchJson("/api/v1/admin/support/tickets/{$ticket->uuid}", ['assigned_to' => $agent->id])->assertOk()->assertJsonPath('data.assignee.id', $agent->id);
    expect(AuditLog::query()->where('action', 'support_ticket.status')->exists())->toBeTrue();

    $this->actingAs($learner);
    $messages = $this->getJson("/api/v1/me/support/tickets/{$ticket->uuid}")->assertOk()->json('data.messages');
    expect($messages)->toHaveCount(2)
        ->and($messages[1]['author'])->toBe('Erudsway Support');

    $this->postJson("/api/v1/me/support/tickets/{$ticket->uuid}/replies", ['message' => 'Still blank, sorry.'])->assertCreated()->assertJsonPath('data.status', 'open');
    $this->postJson("/api/v1/me/support/tickets/{$ticket->uuid}/close")->assertOk()->assertJsonPath('data.status', 'closed');
    $this->postJson("/api/v1/me/support/tickets/{$ticket->uuid}/replies", ['message' => 'One more thing'])
        ->assertUnprocessable()->assertJsonValidationErrorFor('message', 'error.fields');
});

it('keeps the queue to staff with the support permission', function () {
    $this->actingAs(makeStaff(Permission::ManageUsers))->withHeaders(spaHeaders());
    $this->getJson('/api/v1/admin/support/tickets')->assertForbidden();
});
