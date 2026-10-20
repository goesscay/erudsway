<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Learning\Models\Enrollment;
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

it('judges automatic refunds on the most progress ever made, not the current ticks', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();
    $learner = $order->user;
    $lectures = $item->course->lectures()->get();

    // Complete 3 of 5 lectures (60%), then untick them all to get back to 0%.
    foreach ($lectures->take(3) as $l) {
        $this->actingAs($learner)->postJson("/api/v1/lectures/{$l->id}/completion")->assertOk();
    }
    foreach ($lectures->take(3) as $l) {
        $this->actingAs($learner)->deleteJson("/api/v1/lectures/{$l->id}/completion")->assertOk();
    }
    expect(Enrollment::query()->where('user_id', $learner->id)->first())->progress_percent->toBe(0)->peak_progress_percent->toBe(60);

    $this->actingAs($learner)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Changed my mind about it'])
        ->assertJsonPath('data.status', 'requested'); // goes to staff, not automatic
});

it('counts video watched, even when lectures were never ticked', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();
    $learner = $order->user;

    // Watch every video to 89% — short of the 90% that marks it complete.
    foreach ($item->course->lectures()->get() as $l) {
        $this->actingAs($learner)->putJson("/api/v1/lectures/{$l->id}/progress", ['position_seconds' => (int) ($l->duration_seconds * 0.89)])->assertOk();
    }
    expect(Enrollment::query()->where('user_id', $learner->id)->value('progress_percent'))->toBe(0);

    $this->actingAs($learner)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Watched it, want money back'])
        ->assertJsonPath('data.status', 'requested');
});

it('never opens two refunds for one course', function () {
    $order = paidOrder();
    $item = $order->items()->firstOrFail();
    Enrollment::query()->where('user_id', $order->user_id)->update(['progress_percent' => 60, 'peak_progress_percent' => 60]);

    $this->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'First request here'])->assertCreated();
    $this->actingAs($order->user)->postJson("/api/v1/order-items/{$item->id}/refund-request", ['reason' => 'Second request here'])->assertStatus(409);
    expect(Refund::query()->where('order_item_id', $item->id)->count())->toBe(1)
        ->and((int) DB::table('refunds')->sum('amount'))->toBe($item->amount);
});
