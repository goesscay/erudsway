<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Jobs\IssueInvoice;
use App\Modules\Commerce\Jobs\ProcessWebhookEvent;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Commerce\Models\WebhookEvent;
use App\Modules\Commerce\Notifications\OrderReceipt;
use App\Modules\Commerce\Services\InvoiceIssuer;
use App\Modules\Commerce\Services\OrderCompleter;
use App\Modules\Commerce\Services\RefundService;
use App\Modules\Commerce\Support\Gst;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    useRazorpay();
});

/** A pending Razorpay order for a fresh buyer. */
function pendingOrder(): Order
{
    Queue::fake();
    $course = paidCourse();
    $buyer = makeUser();
    test()->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    fakeRazorpay(orderId: 'order_'.Str::random(10));
    $uuid = test()->actingAs($buyer)->postJson('/api/v1/checkout', billing())->json('data.order.id');

    return Order::query()->where('uuid', $uuid)->firstOrFail();
}

function paidOrder(): Order
{
    $order = pendingOrder();
    $gatewayOrder = (string) $order->gateway_order_id;
    fakeRazorpay(['pay_TEST1' => razorpayPayment($order)], $gatewayOrder);
    test()->actingAs($order->user)->postJson("/api/v1/orders/{$order->uuid}/verify", [
        'razorpay_payment_id' => 'pay_TEST1', 'razorpay_order_id' => $gatewayOrder, 'razorpay_signature' => signatureFor($gatewayOrder, 'pay_TEST1'),
    ])->assertOk();

    return $order->refresh();
}

function sendWebhook(array $payload, string $eventId = 'evt_1', ?string $signature = null)
{
    $body = json_encode($payload);

    return test()->call('POST', '/api/v1/webhooks/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, 'webhook_secret'),
        'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
    ], $body);
}

it('completes an order from the payment.captured webhook, once', function () {
    $order = pendingOrder();
    $payload = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => razorpayPayment($order)]]];

    sendWebhook($payload)->assertNoContent();
    sendWebhook($payload)->assertNoContent(); // redelivery
    expect(WebhookEvent::query()->count())->toBe(1);

    Queue::assertPushed(ProcessWebhookEvent::class, 1);
    (new ProcessWebhookEvent(WebhookEvent::query()->value('id')))->handle(app(OrderCompleter::class), app(RefundService::class));

    expect($order->refresh()->status->value)->toBe('paid')
        ->and(Enrollment::query()->where('user_id', $order->user_id)->exists())->toBeTrue()
        ->and(WebhookEvent::query()->value('status'))->toBe('processed');

    // The browser's verify call arriving afterwards changes nothing.
    $gatewayOrder = (string) $order->gateway_order_id;
    fakeRazorpay(['pay_TEST1' => razorpayPayment($order)], $gatewayOrder);
    test()->actingAs($order->user)->postJson("/api/v1/orders/{$order->uuid}/verify", [
        'razorpay_payment_id' => 'pay_TEST1', 'razorpay_order_id' => $gatewayOrder, 'razorpay_signature' => signatureFor($gatewayOrder, 'pay_TEST1'),
    ])->assertOk();
    expect(Payment::query()->count())->toBe(1);
});

it('rejects webhooks with a bad signature', function () {
    sendWebhook(['event' => 'payment.captured'], signature: 'nope')->assertStatus(400);
    expect(WebhookEvent::query()->count())->toBe(0);
});

it('auto-approves early refunds and confirms them by webhook', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();
    fakeRazorpay(['pay_TEST1' => razorpayPayment($order)], (string) $order->gateway_order_id, refundStatus: 'pending');

    $this->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Bought the wrong course by mistake'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.auto_approved', true);

    // Access ends as soon as the refund is issued.
    expect(Enrollment::query()->where('user_id', $order->user_id)->value('status')->value)->toBe('refunded');

    sendWebhook(['event' => 'refund.processed', 'payload' => ['refund' => ['entity' => ['id' => 'rfnd_TEST1', 'status' => 'processed']]]], 'evt_r1');
    (new ProcessWebhookEvent(WebhookEvent::query()->where('event_id', 'evt_r1')->value('id')))->handle(app(OrderCompleter::class), app(RefundService::class));

    expect(Refund::query()->firstOrFail()->status->value)->toBe('processed')
        ->and($order->refresh()->status->value)->toBe('refunded')
        ->and($item->refresh()->refunded_amount)->toBe($item->amount);

    // Only one refund per course.
    $this->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Asking a second time'])->assertStatus(409);
});

it('sends refunds for well-progressed courses to staff, who decide', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();
    Enrollment::query()->where('user_id', $order->user_id)->update(['progress_percent' => 60]);

    $id = $this->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'The course did not cover my state'])
        ->assertJsonPath('data.status', 'requested')
        ->json('data.id');

    $this->actingAs(makeStaff(Permission::ModerateCourses))->getJson('/api/v1/admin/refunds')->assertForbidden();
    $finance = makeStaff(Permission::ManageFinance);
    $this->actingAs($finance)->getJson('/api/v1/admin/refunds')->assertJsonCount(1, 'data')->assertJsonPath('data.0.progress_percent', 60);

    $this->actingAs($finance)->postJson("/api/v1/admin/refunds/{$id}/reject", ['note' => 'short'])->assertUnprocessable();
    $this->actingAs($finance)->postJson("/api/v1/admin/refunds/{$id}/reject", ['note' => 'More than half the course was completed.'])
        ->assertJsonPath('data.status', 'rejected');
    expect(Enrollment::query()->where('user_id', $order->user_id)->value('status')->value)->toBe('active')
        ->and(AuditLog::query()->where('action', 'refund.rejected')->where('actor_id', $finance->id)->exists())->toBeTrue();
});

it('closes the refund window after 30 days and keeps other buyers out', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();

    $this->actingAs(makeUser())->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Not my order but trying'])->assertNotFound();
    $this->travel(31)->days();
    $this->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Changed my mind later'])
        ->assertJsonPath('error.code', 'REFUND_WINDOW_CLOSED');
});

it('issues consecutive GST invoices per financial year with the right tax split', function () {
    Storage::fake('local');
    config(['commerce.seller.state_code' => '33']);
    $first = paidOrder();
    $second = paidOrder();
    $issuer = app(InvoiceIssuer::class);

    $a = $issuer->issue($first);
    $b = $issuer->issue($second);
    expect($a->number)->toMatch('#^ERW/\d{4}/00001$#')
        ->and($b->number)->toMatch('#/00002$#')
        ->and(strlen($a->number))->toBeLessThanOrEqual(16)
        ->and($issuer->issue($first)->id)->toBe($a->id); // idempotent
    Storage::disk('local')->assertExists($a->file_path);
    expect(substr((string) Storage::disk('local')->get($a->file_path), 0, 4))->toBe('%PDF');

    expect(Gst::components(9137, '33'))->toBe(['cgst' => 4568, 'sgst' => 4569, 'igst' => 0])
        ->and(Gst::components(9137, '27'))->toBe(['cgst' => 0, 'sgst' => 0, 'igst' => 9137]);

    $this->actingAs($first->user)->get("/api/v1/orders/{$first->uuid}/invoice")->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('emails the receipt with the invoice once it is issued', function () {
    Storage::fake('local');
    $order = paidOrder();
    (new IssueInvoice($order->id))->handle(app(InvoiceIssuer::class));
    Notification::assertSentTo($order->user, OrderReceipt::class, fn ($n) => $n->invoice !== null);
});

it('validates GSTINs including the check digit', function () {
    expect(Gst::validGstin('27AAPFU0939F1ZV'))->toBeTrue()
        ->and(Gst::validGstin('27AAPFU0939F1ZV', '27'))->toBeTrue()
        ->and(Gst::validGstin('27AAPFU0939F1ZV', '33'))->toBeFalse()
        ->and(Gst::validGstin('27AAPFU0939F1ZX'))->toBeFalse()
        ->and(Gst::financialYear(new DateTimeImmutable('2027-03-31 20:00', new DateTimeZone('Asia/Kolkata'))))->toBe('2026-27')
        ->and(Gst::financialYear(new DateTimeImmutable('2027-04-01 00:30', new DateTimeZone('Asia/Kolkata'))))->toBe('2027-28');
});

it('reconciles a captured payment whose confirmation never arrived', function () {
    $order = pendingOrder();
    fakeRazorpay(['pay_LOST' => razorpayPayment($order, 'pay_LOST'), 'pay_OTHER' => ['id' => 'pay_OTHER', 'order_id' => 'order_UNKNOWN', 'status' => 'captured', 'amount' => 100]]);

    $this->artisan('commerce:reconcile')->expectsOutputToContain('1 orders completed, 1 unmatched')->assertSuccessful();

    expect($order->refresh()->status->value)->toBe('paid')
        ->and(AuditLog::query()->where('action', 'payment.unmatched')->exists())->toBeTrue();
});
