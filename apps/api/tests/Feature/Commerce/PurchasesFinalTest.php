<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Learning\Enums\EnrollmentStatus;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    useRazorpay();
});

it('treats purchases as final: learners can’t request a refund and aren’t offered one', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();

    $this->actingAs($order->user)->getJson("/api/v1/orders/{$order->uuid}")->assertOk()->assertJsonPath('data.items.0.refundable', false);
    $this->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Changed my mind about it'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'PURCHASES_FINAL');

    expect(Refund::query()->count())->toBe(0);
});

it('lets finance staff refund an order themselves, outside any window, with a reason', function () {
    $order = paidOrder();
    $order->forceFill(['paid_at' => now()->subDays(90)])->save();
    $item = $order->items()->firstOrFail();
    $staff = makeStaff(Permission::ManageFinance);

    $this->actingAs(makeStaff(Permission::ModerateCourses))->postJson("/api/v1/admin/order-items/{$item->id}/refund", ['reason' => 'Charged twice for the same order'])
        ->assertForbidden();
    $this->actingAs($staff)->postJson("/api/v1/admin/order-items/{$item->id}/refund", ['reason' => 'short'])->assertUnprocessable();

    $this->actingAs($staff)->postJson("/api/v1/admin/order-items/{$item->id}/refund", ['reason' => 'Charged twice for the same order'])->assertCreated();

    $refund = Refund::query()->sole();
    expect($refund->requested_by)->toBe($order->user_id)
        ->and($refund->approved_by)->toBe($staff->id)
        ->and($refund->status)->not->toBe(RefundStatus::Requested)
        ->and(Enrollment::query()->where('user_id', $order->user_id)->value('status'))->toBe(EnrollmentStatus::Refunded)
        ->and(AuditLog::query()->where('action', 'refund.staff_initiated')->where('actor_id', $staff->id)->exists())->toBeTrue();

    // A second refund of the same course is refused.
    $this->actingAs($staff)->postJson("/api/v1/admin/order-items/{$item->id}/refund", ['reason' => 'Charged twice for the same order'])->assertStatus(409);
});
