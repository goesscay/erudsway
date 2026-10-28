<?php

use App\Modules\Identity\Enums\Permission;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\ContactMessage;
use App\Modules\Platform\Notifications\ContactMessageReceived;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/** @return array<string, mixed> */
function contactBody(array $overrides = []): array
{
    return [
        'name' => 'Divya Raman',
        'email' => ' Divya@Example.com ',
        'phone' => '98765 43210',
        'topic' => 'course',
        'message' => 'Is the GST course available in Tamil, and does it include the simulator?',
        ...$overrides,
    ];
}

it('lists the topics a visitor can choose', function () {
    $this->getJson('/api/v1/contact/topics')->assertOk()
        ->assertJsonPath('data.items.0', ['value' => 'course', 'label' => 'Course enquiry']);
});

it('saves a message and emails it to the support inbox', function () {
    Notification::fake();
    config(['app.support_email' => 'support@erudsway.com']);

    $this->withHeaders(spaHeaders())->postJson('/api/v1/contact', contactBody())->assertCreated();

    $message = ContactMessage::query()->sole();
    expect($message->email)->toBe('divya@example.com')
        ->and($message->phone)->toBe('+91 98765 43210')
        ->and($message->status)->toBe('new');

    Notification::assertSentTo(new AnonymousNotifiable, ContactMessageReceived::class, function ($n, $channels, $notifiable) {
        $mail = $n->toMail($notifiable);

        return $notifiable->routes['mail'] === 'support@erudsway.com' && $mail->replyTo[0][0] === 'divya@example.com';
    });
});

it('checks the details and turns away bots', function () {
    Notification::fake();
    $this->withHeaders(spaHeaders())->postJson('/api/v1/contact', contactBody(['email' => 'not-an-email', 'message' => 'Hi']))
        ->assertUnprocessable()->assertJsonValidationErrorFor('email', 'error.fields')->assertJsonValidationErrorFor('message', 'error.fields');
    $this->withHeaders(spaHeaders())->postJson('/api/v1/contact', contactBody(['website' => 'http://spam.example']))
        ->assertUnprocessable();
    expect(ContactMessage::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('lets staff with the Users permission read and close messages', function () {
    Notification::fake();
    $this->withHeaders(spaHeaders())->postJson('/api/v1/contact', contactBody())->assertCreated();
    $id = ContactMessage::query()->value('uuid');

    $this->actingAs(makeStaff(Permission::ManageFinance))->getJson('/api/v1/admin/contact-messages')->assertForbidden();

    $staff = makeStaff(Permission::ManageUsers);
    $this->actingAs($staff)->getJson('/api/v1/admin/contact-messages')->assertOk()
        ->assertJsonPath('data.new_count', 1)->assertJsonPath('data.items.0.topic_label', 'Course enquiry');

    $this->actingAs($staff)->patchJson("/api/v1/admin/contact-messages/{$id}", ['status' => 'handled'])->assertOk()
        ->assertJsonPath('data.status', 'handled')->assertJsonPath('data.handled_by', $staff->name);
    $this->actingAs($staff)->getJson('/api/v1/admin/contact-messages?status=new')->assertOk()->assertJsonCount(0, 'data.items');
    expect(AuditLog::query()->where('action', 'contact_message.handled')->exists())->toBeTrue();
});
