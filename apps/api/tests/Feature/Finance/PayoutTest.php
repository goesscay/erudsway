<?php

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Models\Payout;
use App\Modules\Finance\Models\PayoutAccount;
use App\Modules\Finance\Notifications\PayoutAccountReviewed;
use App\Modules\Finance\Notifications\PayoutPaid;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    useRazorpay();
    config(['finance.payout_minimum' => 10000, 'finance.tds.rate_bps' => 10]);
});

function bankDetails(array $overrides = []): array
{
    return [
        'legal_name' => 'Vikram Sethi', 'pan' => 'ABCPS1234K', 'account_holder' => 'Vikram Sethi',
        'account_number' => '50100012345678', 'account_number_confirmation' => '50100012345678', 'ifsc' => 'HDFC0001234', ...$overrides,
    ];
}

function verifiedInstructor(User $instructor): void
{
    test()->actingAs($instructor)->putJson('/api/v1/instructor/payout-account', bankDetails())->assertOk();
    test()->actingAs(makeStaff(Permission::ManageFinance))->postJson("/api/v1/admin/payout-accounts/{$instructor->uuid}/verify")->assertOk();
}

/** A paid sale whose earnings have cleared the hold. */
function releasedSale(): User
{
    $order = paidOrder();
    test()->travel(31)->days();
    test()->artisan('finance:release-earnings');

    return User::query()->findOrFail($order->items()->value('instructor_id'));
}

it('keeps payout details masked and re-verifies any change', function () {
    $instructor = paidCourse()->instructor;

    $this->actingAs($instructor)->putJson('/api/v1/instructor/payout-account', bankDetails(['pan' => 'abc', 'ifsc' => 'HDFC1234']))
        ->assertUnprocessable()->assertJsonPath('error.fields.pan.0', 'Enter a PAN like ABCDE1234F.');
    $this->actingAs($instructor)->putJson('/api/v1/instructor/payout-account', bankDetails(['account_number_confirmation' => '1']))->assertUnprocessable();

    $this->actingAs($instructor)->putJson('/api/v1/instructor/payout-account', bankDetails())
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.pan_masked', 'XXXXXX234K')
        ->assertJsonPath('data.account_masked', 'XXXX5678')
        ->assertJsonMissingPath('data.pan');
    $raw = DB::table('payout_accounts')->first();
    expect($raw->account_number)->not->toContain('50100012345678')->and($raw->pan)->not->toContain('ABCPS1234K') // encrypted at rest
        ->and(PayoutAccount::query()->firstOrFail()->account_number)->toBe('50100012345678');

    $finance = makeStaff(Permission::ManageFinance);
    $this->actingAs($finance)->getJson('/api/v1/admin/payout-accounts')->assertJsonCount(1, 'data');
    $this->actingAs($finance)->postJson("/api/v1/admin/payout-accounts/{$instructor->uuid}/verify")->assertJsonPath('data.status', 'verified');
    Notification::assertSentTo($instructor, PayoutAccountReviewed::class);

    // Same details: still verified. New account number: back to review.
    $this->actingAs($instructor)->putJson('/api/v1/instructor/payout-account', bankDetails())->assertJsonPath('data.status', 'verified');
    $this->actingAs($instructor)->putJson('/api/v1/instructor/payout-account', bankDetails(['account_number' => '99990000111122', 'account_number_confirmation' => '99990000111122']))
        ->assertJsonPath('data.status', 'submitted');
});

it('drafts payouts only for verified instructors above the minimum', function () {
    $instructor = releasedSale();
    $finance = makeStaff(Permission::ManageFinance);

    $this->actingAs($finance)->postJson('/api/v1/admin/payouts/generate', ['period_end' => now()->toDateString()])
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.skipped.0.reason', 'No payout details on file');

    verifiedInstructor($instructor);
    config(['finance.payout_minimum' => 10_000_000]);
    $this->actingAs($finance)->postJson('/api/v1/admin/payouts/generate', ['period_end' => now()->toDateString()])
        ->assertJsonPath('data.skipped.0.reason', 'Below the payout minimum; rolls over');

    config(['finance.payout_minimum' => 10000]);
    $this->actingAs($finance)->postJson('/api/v1/admin/payouts/generate', ['period_end' => now()->toDateString()])->assertJsonPath('data.created', 1);

    $payout = Payout::query()->firstOrFail();
    $earned = (int) LedgerEntry::query()->forInstructor($instructor->id)->sum('amount');
    expect($payout->gross)->toBe($earned)
        ->and($payout->tds)->toBe(intdiv($earned * 10, 10000))
        ->and($payout->net)->toBe($payout->gross - $payout->tds)
        ->and($payout->bank_snapshot['account_last4'])->toBe('5678');

    // Running it again doesn't pay the same earnings twice.
    $this->actingAs($finance)->postJson('/api/v1/admin/payouts/generate', ['period_end' => now()->toDateString()])->assertJsonPath('data.created', 0);
});

it('needs a second person to approve, then records the bank transfer', function () {
    $instructor = releasedSale();
    verifiedInstructor($instructor);
    $maker = makeStaff(Permission::ManageFinance);
    $checker = makeStaff(Permission::ManageFinance);
    $this->actingAs($maker)->postJson('/api/v1/admin/payouts/generate', ['period_end' => now()->toDateString()]);
    $payout = Payout::query()->firstOrFail();

    $this->actingAs($maker)->postJson("/api/v1/admin/payouts/{$payout->uuid}/approve")->assertForbidden()->assertJsonPath('error.code', 'SAME_PERSON');
    $this->actingAs($checker)->postJson("/api/v1/admin/payouts/{$payout->uuid}/mark-paid", ['reference' => 'HDFCN52026101912345', 'paid_on' => now()->toDateString()])->assertStatus(409);
    $this->actingAs($checker)->postJson("/api/v1/admin/payouts/{$payout->uuid}/approve")->assertJsonPath('data.status', 'approved');

    $this->actingAs($maker)->postJson("/api/v1/admin/payouts/{$payout->uuid}/mark-paid", ['reference' => 'HDFCN52026101912345', 'paid_on' => now()->toDateString()])
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.reference', 'HDFCN52026101912345');

    expect(LedgerEntry::query()->forInstructor($instructor->id)->where('status', '!=', 'paid')->count())->toBe(0)
        ->and(AuditLog::query()->whereIn('action', ['payout.generated', 'payout.approved', 'payout.paid'])->count())->toBe(3);
    Notification::assertSentTo($instructor, PayoutPaid::class);

    // The instructor sees it, with TDS, in their own history.
    $this->actingAs($instructor)->getJson('/api/v1/instructor/payouts')->assertJsonPath('data.0.status', 'paid');
    $this->actingAs($instructor)->getJson('/api/v1/instructor/revenue')->assertJsonPath('data.balances.paid', $payout->gross)->assertJsonPath('data.balances.available', 0);
});

it('returns earnings to the balance when a payout is cancelled', function () {
    $instructor = releasedSale();
    verifiedInstructor($instructor);
    $finance = makeStaff(Permission::ManageFinance);
    $this->actingAs($finance)->postJson('/api/v1/admin/payouts/generate', ['period_end' => now()->toDateString()]);
    $payout = Payout::query()->firstOrFail();

    $this->actingAs($finance)->postJson("/api/v1/admin/payouts/{$payout->uuid}/cancel", ['reason' => 'Bank details being changed'])->assertJsonPath('data.status', 'cancelled');
    $this->actingAs($instructor)->getJson('/api/v1/instructor/revenue')->assertJsonPath('data.balances.in_payout', 0)
        ->assertJsonPath('data.balances.available', $payout->gross);

    // The same earnings can go into a new payout.
    $this->actingAs($finance)->postJson('/api/v1/admin/payouts/generate', ['period_end' => now()->toDateString()])->assertJsonPath('data.created', 1);
});

it('shows instructors their revenue, statement and course breakdown', function () {
    $order = paidOrder();
    $instructor = User::query()->findOrFail($order->items()->value('instructor_id'));

    $this->actingAs(makeUser())->getJson('/api/v1/instructor/revenue')->assertForbidden();
    $res = $this->actingAs($instructor)->getJson('/api/v1/instructor/revenue')->assertOk();
    expect($res->json('data.balances.pending'))->toBeGreaterThan(0)
        ->and($res->json('data.courses.0.sales'))->toBe(1)
        ->and($res->json('data.monthly'))->toHaveCount(12)
        ->and($res->json('data.next_release_at'))->not->toBeNull();

    $this->actingAs($instructor)->getJson('/api/v1/instructor/statement')->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'sale');
});

it('reports the period and exports the ledger for the accountants', function () {
    $order = paidOrder();
    $finance = makeStaff(Permission::ManageFinance);

    $res = $this->actingAs($finance)->getJson('/api/v1/admin/finance/summary')->assertOk();
    expect($res->json('data.sales.count'))->toBe(1)
        ->and($res->json('data.sales.collected'))->toBe($order->total)
        ->and(array_sum($res->json('data.split')))->toBe($order->total)
        ->and($res->json('data.instructors.0.sales'))->toBe(1);

    $csv = $this->actingAs($finance)->get('/api/v1/admin/finance/ledger.csv')->assertOk()->streamedContent();
    expect(substr_count($csv, "\n"))->toBe(5) // header + 4 lines
        ->and($csv)->toContain('gateway_fee');

    $this->actingAs(makeUser())->getJson('/api/v1/admin/finance/summary')->assertForbidden();
});
