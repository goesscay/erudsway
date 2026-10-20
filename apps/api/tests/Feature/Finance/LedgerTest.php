<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Commerce\Jobs\ProcessWebhookEvent;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\WebhookEvent;
use App\Modules\Commerce\Services\OrderCompleter;
use App\Modules\Commerce\Services\RefundService;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\Balances;
use App\Modules\Finance\Services\CommissionRules;
use App\Modules\Identity\Enums\Permission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // These tests cover the learner refund flow, which is off by default (purchases are final).
    config(['commerce.refunds.self_service' => true]);
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    useRazorpay();
});

function refundOf($order): void
{
    $item = $order->items()->firstOrFail();
    fakeRazorpay(['pay_TEST1' => razorpayPayment($order)], (string) $order->gateway_order_id);
    test()->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Bought the wrong course'])->assertCreated();
    sendWebhook(['event' => 'refund.processed', 'payload' => ['refund' => ['entity' => ['id' => 'rfnd_TEST1']]]], 'evt_'.$order->id);
    (new ProcessWebhookEvent(WebhookEvent::query()->latest('id')->value('id')))->handle(app(OrderCompleter::class), app(RefundService::class));
}

it('splits every sale into instructor, platform, GST and fee lines that add up', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();
    $lines = LedgerEntry::query()->where('order_item_id', $item->id)->get()->keyBy(fn ($e) => $e->account->value);

    expect($lines)->toHaveCount(4)
        ->and((int) $lines->sum('amount'))->toBe($item->amount)
        ->and($lines['tax']->amount)->toBe($item->tax)
        ->and($lines['gateway_fee']->amount)->toBe(1416)
        ->and($lines['instructor']->amount)->toBe($item->instructor_amount)
        ->and($lines['instructor']->status->value)->toBe('pending')
        ->and($order->paid_at->diffInDays($lines['instructor']->available_at))->toEqual(30)
        ->and($lines['instructor']->instructor_id)->toBe($item->instructor_id);
});

it('never edits or deletes ledger amounts', function () {
    paidOrder();
    $entry = LedgerEntry::query()->firstOrFail();

    expect(fn () => $entry->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        // The database enforces it too, even bypassing the model. (Each attempt in its own
        // savepoint: on PostgreSQL a failed statement otherwise aborts the test's transaction.)
        ->and(fn () => DB::transaction(fn () => DB::table('ledger_entries')->where('id', $entry->id)->update(['amount' => 1])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('ledger_entries')->where('id', $entry->id)->delete()))->toThrow(QueryException::class);

    $entry->refresh()->update(['status' => 'available']); // allowed: state, not money
    expect($entry->refresh()->status->value)->toBe('available');
});

it('reverses a refund that is still on hold so the two release together', function () {
    $order = paidOrder();
    $instructorId = $order->items()->value('instructor_id');
    refundOf($order);

    $balances = app(Balances::class);
    expect($balances->for($instructorId))->toMatchArray(['pending' => 0, 'available' => 0, 'lifetime' => 0]);

    // Platform absorbs the gateway fee; GST is reversed in full.
    $byAccount = LedgerEntry::query()->selectRaw('account, SUM(amount) as total')->groupBy('account')->pluck('total', 'account');
    expect((int) $byAccount['tax'])->toBe(0)
        ->and((int) $byAccount['gateway_fee'])->toBe(1416)
        ->and((int) $byAccount['platform'])->toBe(-1416);
});

it('nets a refund after release against the available balance', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();
    $this->travel(31)->days();
    $this->artisan('finance:release-earnings')->assertSuccessful();
    expect(app(Balances::class)->for($item->instructor_id)['available'])->toBe($item->instructor_amount);

    // Refund outside the learner window isn't possible, so issue it as staff would via a processed refund.
    $this->travelBack();
    $refund = $item->refunds()->create(['amount' => $item->amount, 'status' => 'processing', 'reason' => 'Goodwill refund for a duplicate purchase', 'requested_by' => $order->user_id]);
    app(RefundService::class)->markProcessed($refund);

    $line = LedgerEntry::query()->where('refund_id', $refund->id)->where('account', 'instructor')->firstOrFail();
    expect($line->status->value)->toBe('available')
        ->and($line->amount)->toBe(-$item->instructor_amount)
        ->and(app(Balances::class)->for($item->instructor_id)['available'])->toBe(0);
});

it('applies commission rules at checkout, instructor overrides first', function () {
    $course = paidCourse();
    $admin = makeStaff(Permission::ManageFinance);
    $rules = app(CommissionRules::class);

    expect($rules->rateFor(SaleChannel::Organic, $course->instructor_id))->toBe(5000); // config default (D2)

    $rules->set($admin, SaleChannel::Organic, null, 5500);
    expect($rules->rateFor(SaleChannel::Organic, $course->instructor_id))->toBe(5500);

    $rules->set($admin, SaleChannel::Organic, $course->instructor_id, 7000);
    $rules->set($admin, SaleChannel::Organic, null, 6000); // replaces the 55% default
    expect($rules->rateFor(SaleChannel::Organic, $course->instructor_id))->toBe(7000)
        ->and($rules->rateFor(SaleChannel::Organic, makeUser()->id))->toBe(6000);

    // Snapshotted on the order item at checkout.
    $buyer = makeUser();
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug]);
    fakeRazorpay();
    $this->actingAs($buyer)->postJson('/api/v1/checkout', billing())->assertCreated();
    expect(OrderItem::query()->latest('id')->value('commission_rate_bps'))->toBe(7000);
});

it('manages commission rules from the finance console only', function () {
    $finance = makeStaff(Permission::ManageFinance);
    $instructor = paidCourse()->instructor;

    $this->actingAs(makeStaff(Permission::ModerateCourses))->getJson('/api/v1/admin/commission-rules')->assertForbidden();
    $id = $this->actingAs($finance)->postJson('/api/v1/admin/commission-rules', ['channel' => 'organic', 'instructor_email' => $instructor->email, 'instructor_percent' => 65])
        ->assertCreated()->assertJsonPath('data.instructor_percent', 65)->json('data.id');
    $this->actingAs($finance)->postJson('/api/v1/admin/commission-rules', ['channel' => 'organic', 'instructor_email' => 'nobody@example.com', 'instructor_percent' => 65])->assertUnprocessable();

    $this->actingAs($finance)->postJson("/api/v1/admin/commission-rules/{$id}/end")->assertJsonPath('data.active', false);
});
