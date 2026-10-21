<?php

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Jobs\IssueInvoice;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Notification::fake();
    Queue::fake();
    Http::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    useRazorpay();
});

/** A live course in two editions: ₹7,800 (1 year) and ₹12,100. */
function offlineCourse(): Course
{
    $course = liveCourse(free: false);
    $tier = fn (int $rupees) => PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => $rupees * 100])->id;
    $course->editions()->create(['position' => 1, 'name' => 'Video Course', 'price_tier_id' => $tier(7800), 'includes' => ['Videos'], 'access_days' => 365]);
    $course->editions()->create(['position' => 2, 'name' => 'Course + Kit', 'price_tier_id' => $tier(12100), 'includes' => ['Kit'], 'access_days' => 365]);
    $course->forceFill(['price_tier_id' => $tier(7800)])->save();

    return $course->fresh();
}

/** @return array<string, mixed> */
function offlineBody(User $buyer, Course $course, array $overrides = []): array
{
    return [
        'user' => $buyer->uuid,
        'course' => $course->slug,
        'edition_id' => $course->editions()->where('position', 2)->value('id'),
        'amount' => 1_000_000,
        'method' => 'upi',
        'reference' => 'UTR 412345678901',
        'received_on' => now()->toDateString(),
        'note' => 'Paid at the Chennai workshop',
        'name' => $buyer->name,
        'state_code' => '33',
        ...$overrides,
    ];
}

it('records an offline payment as a paid order: access, invoice, and no instructor share', function () {
    $course = offlineCourse();
    $buyer = makeUser();
    $staff = makeStaff(Permission::ManageFinance);

    $this->actingAs($staff)->getJson('/api/v1/admin/offline-payments/quote?'.http_build_query(['user' => $buyer->uuid, 'course' => $course->slug, 'edition_id' => $course->editions()->where('position', 2)->value('id')]))
        ->assertOk()->assertJsonPath('data.list_price', 1_210_000)->assertJsonPath('data.problem', null);

    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody($buyer, $course))
        ->assertCreated()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.total', 1_000_000)
        ->assertJsonPath('data.discount_total', 210_000)
        ->assertJsonPath('data.offline_payment.method_label', 'UPI')
        ->assertJsonPath('data.offline_payment.reference', 'UTR 412345678901');

    $order = Order::query()->where('user_id', $buyer->id)->sole();
    $item = $order->items()->sole();
    expect($order->gateway)->toBe('offline')
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($item->instructor_amount)->toBe(0)
        ->and($item->platform_amount)->toBe($item->net_amount)
        ->and(Payment::query()->where('order_id', $order->id)->sole()->raw)->toMatchArray(['recorded_by' => $staff->id, 'method' => 'upi']);

    $enrollment = Enrollment::query()->where('user_id', $buyer->id)->where('course_id', $course->id)->sole();
    expect($enrollment->edition_id)->toBe($item->edition_id)->and($enrollment->expires_at)->not->toBeNull();

    expect(LedgerEntry::query()->where('order_item_id', $item->id)->where('account', 'instructor')->exists())->toBeFalse()
        ->and((int) LedgerEntry::query()->where('order_item_id', $item->id)->where('account', 'platform')->sum('amount'))->toBe($item->net_amount);

    Queue::assertPushed(IssueInvoice::class);
    expect(AuditLog::query()->where('action', 'order.offline_payment_recorded')->sole()->actor_id)->toBe($staff->id);
    Http::assertNothingSent();
});

it('charges only the difference when the learner already has a lower edition', function () {
    $course = offlineCourse();
    $buyer = makeUser();
    $staff = makeStaff(Permission::ManageFinance);
    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody($buyer, $course, [
        'edition_id' => $course->editions()->where('position', 1)->value('id'), 'amount' => 780_000,
    ]))->assertCreated();

    $this->actingAs($staff)->getJson('/api/v1/admin/offline-payments/quote?'.http_build_query(['user' => $buyer->uuid, 'course' => $course->slug, 'edition_id' => $course->editions()->where('position', 2)->value('id')]))
        ->assertOk()->assertJsonPath('data.list_price', 430_000)->assertJsonPath('data.upgrade_from', 'Video Course');

    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody($buyer, $course, ['amount' => 430_001]))
        ->assertUnprocessable()->assertJsonPath('error.code', 'AMOUNT_OUT_OF_RANGE');
    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody($buyer, $course, ['amount' => 430_000]))
        ->assertCreated();

    expect(Enrollment::query()->where('user_id', $buyer->id)->sole()->edition_id)->toBe($course->editions()->where('position', 2)->value('id'));
});

it('refuses what can’t be sold, and checks the details', function () {
    $course = offlineCourse();
    $buyer = makeUser();
    $staff = makeStaff(Permission::ManageFinance);
    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody($buyer, $course))->assertCreated();

    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody($buyer, $course))
        ->assertUnprocessable()->assertJsonPath('error.code', 'EDITION_OWNED');
    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody(makeUser(), $course, ['amount' => 50]))
        ->assertUnprocessable()->assertJsonValidationErrorFor('amount', 'error.fields');
    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody(makeUser(), $course, ['received_on' => now()->addDay()->toDateString()]))
        ->assertUnprocessable()->assertJsonValidationErrorFor('received_on', 'error.fields');
    $this->actingAs($staff)->postJson('/api/v1/admin/offline-payments', offlineBody(makeUser(), $course, ['gstin' => '27AAAAA0000A1Z5', 'business_name' => 'X', 'address' => 'Y']))
        ->assertUnprocessable()->assertJsonValidationErrorFor('gstin', 'error.fields');
});

it('finds learners by name or email for finance staff', function () {
    User::factory()->create(['name' => 'Kavya Iyer', 'email' => 'kavya@example.com']);

    $this->actingAs(makeStaff(Permission::ManageFinance))->getJson('/api/v1/admin/offline-payments/learners?q=KAV')
        ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.email', 'kavya@example.com');
    $this->actingAs(makeStaff(Permission::ManageUsers))->getJson('/api/v1/admin/offline-payments/learners?q=kav')
        ->assertForbidden();
});

it('is for finance staff only', function () {
    $course = offlineCourse();

    $this->actingAs(makeStaff(Permission::ManageUsers))->postJson('/api/v1/admin/offline-payments', offlineBody(makeUser(), $course))
        ->assertForbidden();
    $this->actingAs(makeUser(Role::Admin))->postJson('/api/v1/admin/offline-payments', offlineBody(makeUser(), $course))
        ->assertCreated();
});

it('refunds an offline order without contacting the payment gateway', function () {
    $course = offlineCourse();
    $buyer = makeUser();
    $admin = makeUser(Role::Admin);
    $this->actingAs($admin)->postJson('/api/v1/admin/offline-payments', offlineBody($buyer, $course))->assertCreated();
    $item = Order::query()->where('user_id', $buyer->id)->sole()->items()->sole();

    $this->actingAs($admin)->postJson("/api/v1/admin/order-items/{$item->id}/refund", ['reason' => 'Returned in cash at the office'])
        ->assertCreated()->assertJsonPath('data.status', 'processed');

    Http::assertNothingSent();
    expect(Order::query()->where('user_id', $buyer->id)->sole()->status)->toBe(OrderStatus::Refunded);
});
