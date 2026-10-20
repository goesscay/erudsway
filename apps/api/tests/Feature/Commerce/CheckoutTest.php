<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Jobs\IssueInvoice;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Payment;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    useRazorpay();
});

it('keeps a guest cart by token and merges it on sign-in', function () {
    $course = paidCourse();

    $token = $this->postJson('/api/v1/cart/items', ['course' => $course->slug])
        ->assertOk()
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.totals.total', 59900)
        ->json('data.token');
    expect($token)->toBeString()->toHaveLength(48);

    $this->getJson('/api/v1/cart', ['X-Cart-Token' => $token])->assertJsonPath('data.count', 1);
    $this->getJson('/api/v1/cart')->assertJsonPath('data.count', 0);

    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/merge', ['token' => $token])
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.token', null);
    $this->getJson('/api/v1/cart', ['X-Cart-Token' => $token])->assertJsonPath('data.count', 1); // now the user's
});

it('refuses free, own and already-owned courses', function () {
    $free = liveCourse(free: true);
    $this->postJson('/api/v1/cart/items', ['course' => $free->slug])->assertUnprocessable()->assertJsonPath('error.code', 'COURSE_FREE');

    $paid = paidCourse();
    $this->actingAs($paid->instructor)->postJson('/api/v1/cart/items', ['course' => $paid->slug])->assertJsonPath('error.code', 'OWN_COURSE');

    $owner = makeUser();
    enrollIn($paid, $owner);
    $this->actingAs($owner)->postJson('/api/v1/cart/items', ['course' => $paid->slug])->assertJsonPath('error.code', 'ALREADY_ENROLLED');
});

it('applies coupons and explains codes that cannot be used', function () {
    $course = paidCourse();
    Coupon::query()->create(['code' => 'GST20', 'kind' => 'percent', 'value' => 20, 'channel' => 'platform_promoted']);
    Coupon::query()->create(['code' => 'OLD', 'kind' => 'percent', 'value' => 50, 'ends_at' => now()->subDay()]);
    $user = makeUser();
    $this->actingAs($user)->postJson('/api/v1/cart/items', ['course' => $course->slug]);

    $this->actingAs($user)->postJson('/api/v1/cart/coupon', ['code' => 'gst20'])
        ->assertJsonPath('data.coupon.applied', true)
        ->assertJsonPath('data.totals.discount', 11980)
        ->assertJsonPath('data.totals.total', 47920)
        // GST is inside the price: 47920 = 40610 taxable + 7310 tax at 18%.
        ->assertJsonPath('data.totals.tax', 7310);

    $this->actingAs($user)->postJson('/api/v1/cart/coupon', ['code' => 'OLD'])
        ->assertJsonPath('data.coupon.applied', false)
        ->assertJsonPath('data.coupon.message', 'That code has expired.')
        ->assertJsonPath('data.totals.total', 59900);
});

it('creates a server-priced order and opens a Razorpay order', function () {
    fakeRazorpay();
    $course = paidCourse();
    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);

    $res = $this->actingAs($buyer)->postJson('/api/v1/checkout', billing())
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.order.total', 59900)
        ->assertJsonPath('data.payment.gateway', 'razorpay')
        ->assertJsonPath('data.payment.key', 'rzp_test_key')
        ->assertJsonPath('data.payment.gateway_order_id', 'order_TEST1')
        ->assertJsonPath('data.payment.fake_signature', null);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/orders') && $r['amount'] === 59900 && $r['currency'] === 'INR');
    expect(Order::query()->firstOrFail())
        ->billing_snapshot->toMatchArray(['state' => 'Tamil Nadu', 'email' => $buyer->email]);
    expect($res->json('data.order.items.0.amount'))->toBe(59900);
});

it('requires a verified email and valid billing details', function () {
    fakeRazorpay();
    $course = paidCourse();
    $unverified = makeUser(verified: false);
    $this->actingAs($unverified)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    $this->actingAs($unverified)->postJson('/api/v1/checkout', billing())->assertForbidden()->assertJsonPath('error.code', 'EMAIL_UNVERIFIED');

    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    $this->actingAs($buyer)->postJson('/api/v1/checkout', billing(['state_code' => '99']))->assertUnprocessable();
    $this->actingAs($buyer)->postJson('/api/v1/checkout', billing(['gstin' => '27AAPFU0939F1ZV', 'business_name' => 'X', 'address' => 'Y']))
        ->assertUnprocessable()->assertJsonPath('error.fields.gstin.0', 'This GSTIN isn’t valid for the selected state.');
});

it('grants access only after a verified, captured payment — once', function () {
    $course = paidCourse();
    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    fakeRazorpay();
    $uuid = $this->actingAs($buyer)->postJson('/api/v1/checkout', billing())->json('data.order.id');
    $order = Order::query()->where('uuid', $uuid)->firstOrFail();
    fakeRazorpay(['pay_TEST1' => razorpayPayment($order)]);

    // A forged signature is refused and grants nothing.
    $this->actingAs($buyer)->postJson("/api/v1/orders/{$uuid}/verify", [
        'razorpay_payment_id' => 'pay_TEST1', 'razorpay_order_id' => 'order_TEST1', 'razorpay_signature' => 'forged',
    ])->assertUnprocessable();
    expect(Enrollment::query()->count())->toBe(0);

    $payload = ['razorpay_payment_id' => 'pay_TEST1', 'razorpay_order_id' => 'order_TEST1', 'razorpay_signature' => signatureFor('order_TEST1', 'pay_TEST1')];
    $this->actingAs($buyer)->postJson("/api/v1/orders/{$uuid}/verify", $payload)->assertOk()->assertJsonPath('data.status', 'paid');
    $this->actingAs($buyer)->postJson("/api/v1/orders/{$uuid}/verify", $payload)->assertOk(); // replay: no-op

    expect(Payment::query()->count())->toBe(1)
        ->and(Enrollment::query()->where('user_id', $buyer->id)->where('source', 'purchase')->count())->toBe(1)
        ->and($course->fresh()->enrollment_count)->toBe(1)
        ->and(AuditLog::query()->where('action', 'order.paid')->count())->toBe(1);
    Queue::assertPushed(IssueInvoice::class, 1);

    // Cart emptied; commission snapshot uses net of GST and the gateway fee (D2/D3).
    $this->actingAs($buyer)->getJson('/api/v1/cart')->assertJsonPath('data.count', 0);
    $item = $order->items()->firstOrFail();
    expect($item->net_amount)->toBe(50763)
        ->and($item->gateway_fee)->toBe(1416)
        ->and($item->instructor_amount + $item->platform_amount)->toBe(50763 - 1416)
        ->and($item->instructor_amount)->toBe(intdiv((50763 - 1416) * 5000, 10000)); // organic sale: 50%
});

it('refuses a payment that does not match the order', function () {
    $course = paidCourse();
    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    fakeRazorpay();
    $uuid = $this->actingAs($buyer)->postJson('/api/v1/checkout', billing())->json('data.order.id');
    $order = Order::query()->where('uuid', $uuid)->firstOrFail();
    fakeRazorpay(['pay_LOW' => razorpayPayment($order, 'pay_LOW', amount: 100)]);

    $this->actingAs($buyer)->postJson("/api/v1/orders/{$uuid}/verify", [
        'razorpay_payment_id' => 'pay_LOW', 'razorpay_order_id' => 'order_TEST1', 'razorpay_signature' => signatureFor('order_TEST1', 'pay_LOW'),
    ])->assertStatus(409)->assertJsonPath('error.code', 'PAYMENT_MISMATCH');

    expect(Enrollment::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'payment.mismatch')->exists())->toBeTrue();
});

it('completes a fully discounted order without a payment', function () {
    $course = paidCourse();
    Coupon::query()->create(['code' => 'SCHOLAR', 'kind' => 'percent', 'value' => 100, 'max_redemptions' => 1]);
    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    $this->actingAs($buyer)->postJson('/api/v1/cart/coupon', ['code' => 'SCHOLAR']);

    $this->actingAs($buyer)->postJson('/api/v1/checkout', billing())
        ->assertCreated()
        ->assertJsonPath('data.order.status', 'paid')
        ->assertJsonPath('data.order.total', 0)
        ->assertJsonPath('data.payment', null)
        ->assertJsonPath('data.order.invoice', null);

    expect(Enrollment::query()->where('user_id', $buyer->id)->exists())->toBeTrue();
    Http::assertNothingSent();

    // The single use is gone.
    $other = makeUser();
    $this->actingAs($other)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    $this->actingAs($other)->postJson('/api/v1/cart/coupon', ['code' => 'SCHOLAR'])->assertJsonPath('data.coupon.message', 'That code has been fully used.');
});

it('lists purchase history and keeps orders private', function () {
    $course = paidCourse();
    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    fakeRazorpay();
    $uuid = $this->actingAs($buyer)->postJson('/api/v1/checkout', billing())->json('data.order.id');
    $order = Order::query()->where('uuid', $uuid)->firstOrFail();
    fakeRazorpay(['pay_TEST1' => razorpayPayment($order)]);
    $this->actingAs($buyer)->postJson("/api/v1/orders/{$uuid}/verify", ['razorpay_payment_id' => 'pay_TEST1', 'razorpay_order_id' => 'order_TEST1', 'razorpay_signature' => signatureFor('order_TEST1', 'pay_TEST1')]);

    $this->actingAs($buyer)->getJson('/api/v1/orders')->assertJsonCount(1, 'data')->assertJsonPath('data.0.items.0.refundable', false); // purchases are final by default
    $this->actingAs(makeUser())->getJson("/api/v1/orders/{$uuid}")->assertNotFound();
    $this->actingAs(makeUser())->get("/api/v1/orders/{$uuid}/invoice")->assertNotFound();
});

it('lets finance staff create and switch off coupons, audited', function () {
    $course = paidCourse();
    $finance = makeStaff(Permission::ManageFinance);

    $this->actingAs(makeUser())->postJson('/api/v1/admin/coupons', ['code' => 'LAUNCH25', 'kind' => 'percent', 'value' => 25])->assertForbidden();
    $this->actingAs($finance)->postJson('/api/v1/admin/coupons', ['code' => 'LAUNCH', 'kind' => 'percent', 'value' => 150])->assertUnprocessable();

    $id = $this->actingAs($finance)->postJson('/api/v1/admin/coupons', ['code' => 'launch25', 'kind' => 'percent', 'value' => 25, 'course' => $course->slug])
        ->assertCreated()
        ->assertJsonPath('data.code', 'LAUNCH25')
        ->assertJsonPath('data.channel', 'platform_promoted')
        ->json('data.id');
    $this->actingAs($finance)->postJson('/api/v1/admin/coupons', ['code' => 'LAUNCH25', 'kind' => 'fixed', 'value' => 100])->assertUnprocessable();

    $this->actingAs($finance)->patchJson("/api/v1/admin/coupons/{$id}", ['is_active' => false])->assertJsonPath('data.is_active', false);
    expect(AuditLog::query()->whereIn('action', ['coupon.created', 'coupon.updated'])->count())->toBe(2);
});
