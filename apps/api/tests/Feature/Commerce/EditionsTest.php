<?php

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Models\PriceTier;
use App\Modules\Commerce\Models\Order;
use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Jobs\ProvisionSimulatorAccess;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Fulfilment\Notifications\DoubtSessionScheduled;
use App\Modules\Fulfilment\Notifications\SimulatorAccessReady;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();
    Queue::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    config(['services.simulator.url' => 'https://sim.test', 'services.simulator.key' => 'sim_key']);

    // The simulator answers its own host; everything else falls through to the Razorpay fake.
    Http::fake(function ($request) {
        if (parse_url($request->url(), PHP_URL_HOST) !== 'sim.test') {
            return null;
        }
        expect($request->header('X-API-Key')[0] ?? null)->toBe('sim_key');

        return match (parse_url($request->url(), PHP_URL_PATH)) {
            '/api/integration/v1/gst/users' => Http::response(['id' => 77, 'username' => $request['username']], 201),
            '/api/integration/v1/sso' => Http::response(['url' => 'https://sim.test/sso?code=abc', 'expires_in' => 60]),
            default => Http::response([], 404),
        };
    });
    useRazorpay();
});

/** A live course sold in three editions, with lecture 5 held back to "Course + Kit". */
function editionCourse(): Course
{
    $course = liveCourse(free: false);
    $tier = fn (int $rupees) => PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => $rupees * 100])->id;
    $video = $course->editions()->create(['position' => 1, 'name' => 'Video Course', 'price_tier_id' => $tier(7800), 'includes' => ['Videos', 'MCQs'], 'access_days' => 365, 'video_view_limit' => 1]);
    $kit = $course->editions()->create(['position' => 2, 'name' => 'Course + Kit', 'price_tier_id' => $tier(8500), 'includes' => ['PDF notes'], 'is_recommended' => true, 'access_days' => 365, 'video_view_limit' => 1]);
    $course->editions()->create(['position' => 3, 'name' => 'Simulator Mastery', 'price_tier_id' => $tier(14700), 'includes' => ['GST simulator', 'Doubt session'], 'access_days' => 365, 'video_view_limit' => 2, 'grants_simulator' => true, 'includes_doubt_session' => true]);
    $course->lectures()->orderBy('position')->get()->last()->forceFill(['min_edition_id' => $kit->id])->save();
    $course->forceFill(['price_tier_id' => $tier(7800)])->save();
    unset($video);

    return $course->fresh();
}

function edition(Course $course, string $name): CourseEdition
{
    return $course->editions()->where('name', $name)->sole();
}

/** Put an edition in the cart, check out and pay. */
function buyEdition(User $buyer, Course $course, CourseEdition $edition): Order
{
    test()->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug, 'edition' => $edition->id])->assertOk();
    $gatewayOrder = 'order_'.Str::random(10);
    fakeRazorpay(orderId: $gatewayOrder);
    $uuid = test()->actingAs($buyer)->postJson('/api/v1/checkout', billing())->assertCreated()->json('data.order.id');
    $order = Order::query()->where('uuid', $uuid)->firstOrFail();
    $paymentId = 'pay_'.Str::random(8);
    fakeRazorpay([$paymentId => razorpayPayment($order, $paymentId)], $gatewayOrder);
    test()->actingAs($buyer)->postJson("/api/v1/orders/{$order->uuid}/verify", [
        'razorpay_payment_id' => $paymentId, 'razorpay_order_id' => $gatewayOrder, 'razorpay_signature' => signatureFor($gatewayOrder, $paymentId),
    ])->assertOk();

    return $order->refresh();
}

it('lists editions on the course page and prices the cart by the chosen one', function () {
    $course = editionCourse();

    $this->getJson("/api/v1/courses/{$course->slug}")->assertOk()
        ->assertJsonCount(3, 'data.editions')
        ->assertJsonPath('data.editions.1.is_recommended', true)
        ->assertJsonPath('data.editions.2.grants_simulator', true)
        ->assertJsonPath('data.curriculum.0.lectures.4.edition', 'Course + Kit');

    $buyer = makeUser();
    // No edition chosen: the recommended one.
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug])->assertOk()
        ->assertJsonPath('data.items.0.edition.name', 'Course + Kit')->assertJsonPath('data.items.0.amount', 850000);
    // Adding again with another edition switches the line.
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug, 'edition' => edition($course, 'Video Course')->id])->assertOk()
        ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.amount', 780000);
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug, 'edition' => 999999])->assertUnprocessable();
});

it('gives the bought edition, its access period and locks content above it', function () {
    $course = editionCourse();
    $buyer = makeUser();
    $order = buyEdition($buyer, $course, edition($course, 'Video Course'));

    expect($order->items()->first()->course_title)->toEndWith('— Video Course');
    $enrollment = Enrollment::query()->where('user_id', $buyer->id)->sole();
    expect($enrollment->edition_id)->toBe(edition($course, 'Video Course')->id)
        ->and($enrollment->expires_at->diffInDays(now()->addDays(365)))->toBeLessThan(1);

    $locked = $course->lectures()->orderBy('position')->get()->last();
    $this->actingAs($buyer)->getJson("/api/v1/learn/lectures/{$locked->id}")->assertForbidden()->assertJsonPath('error.code', 'EDITION_LOCKED');
    $this->actingAs($buyer)->postJson("/api/v1/lectures/{$locked->id}/playback")->assertForbidden()->assertJsonPath('error.code', 'EDITION_LOCKED');

    $this->actingAs($buyer)->getJson("/api/v1/learn/{$course->slug}")->assertOk()
        ->assertJsonPath('data.enrollment.edition.name', 'Video Course')
        ->assertJsonPath('data.curriculum.0.lectures.4.locked_until.name', 'Course + Kit')
        ->assertJsonPath('data.curriculum.0.lectures.0.locked_until', null)
        ->assertJsonPath('data.upgrades.0.upgrade_price', 70000)
        ->assertJsonPath('data.upgrades.1.upgrade_price', 690000);

    // The instructor always sees everything.
    $this->actingAs($course->instructor)->getJson("/api/v1/learn/lectures/{$locked->id}")->assertOk();
});

it('upgrades for the price difference, then opens the content and the extras', function () {
    $course = editionCourse();
    $buyer = makeUser();
    buyEdition($buyer, $course, edition($course, 'Video Course'));

    $top = edition($course, 'Simulator Mastery');
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug, 'edition' => $top->id])->assertOk()
        ->assertJsonPath('data.items.0.amount', 690000)
        ->assertJsonPath('data.items.0.upgrade_from.name', 'Video Course');
    // Buying the same (or a lower) edition again isn't possible.
    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug, 'edition' => edition($course, 'Video Course')->id])->assertUnprocessable();

    $this->actingAs($buyer)->postJson('/api/v1/cart/items', ['course' => $course->slug, 'edition' => $top->id])->assertOk();
    $upgrade = buyEdition($buyer, $course, $top);
    expect($upgrade->total)->toBe(690000)
        ->and($upgrade->items()->first()->upgrade_from_edition_id)->toBe(edition($course, 'Video Course')->id)
        ->and(Enrollment::query()->where('user_id', $buyer->id)->value('edition_id'))->toBe($top->id);

    $locked = $course->lectures()->orderBy('position')->get()->last();
    $this->actingAs($buyer)->getJson("/api/v1/learn/lectures/{$locked->id}")->assertOk();

    // Simulator access is provisioned; a doubt session waits for the learner's request.
    Queue::assertPushed(ProvisionSimulatorAccess::class, 1);
    $simulator = FulfilmentTask::query()->where('type', TaskType::SimulatorAccess->value)->sole();
    app()->call([new ProvisionSimulatorAccess($simulator), 'handle']);
    $simulator->refresh();
    expect($simulator->status)->toBe(TaskStatus::Done)->and($simulator->data['username'])->toStartWith('erw');
    Notification::assertSentTo($buyer, SimulatorAccessReady::class);

    $this->actingAs($buyer)->getJson("/api/v1/me/courses/{$course->slug}/extras")->assertOk()
        ->assertJsonPath('data.simulator.status', 'ready')
        ->assertJsonPath('data.simulator.username', $simulator->data['username'])
        ->assertJsonPath('data.doubt_session.status', 'awaiting_learner');
    $this->actingAs($buyer)->postJson("/api/v1/me/courses/{$course->slug}/simulator-link")->assertOk()->assertJsonPath('data.url', 'https://sim.test/sso?code=abc');

    $this->actingAs($buyer)->postJson("/api/v1/me/courses/{$course->slug}/doubt-session", ['preferred_times' => 'Weekday evenings', 'questions' => 'ITC reversal on credit notes for GSTR-3B'])
        ->assertOk()->assertJsonPath('data.doubt_session.status', 'requested');

    // Staff see both in the queue and schedule the session.
    $staff = makeStaff(Permission::ManageFulfilment);
    $this->actingAs(makeUser(Role::Instructor))->getJson('/api/v1/admin/fulfilment')->assertForbidden();
    $rows = $this->actingAs($staff)->getJson('/api/v1/admin/fulfilment')->assertOk()->json('data');
    expect(collect($rows)->pluck('type')->all())->toBe(['doubt_session']);
    $doubt = FulfilmentTask::query()->where('type', TaskType::DoubtSession->value)->sole();
    $this->actingAs($staff)->patchJson("/api/v1/admin/fulfilment/{$doubt->uuid}", ['status' => 'scheduled', 'scheduled_for' => now()->addDays(2)->toIso8601String(), 'staff_notes' => 'Google Meet link to follow'])
        ->assertOk()->assertJsonPath('data.status', 'scheduled');
    Notification::assertSentTo($buyer, DoubtSessionScheduled::class);
    $this->actingAs($buyer)->getJson("/api/v1/me/courses/{$course->slug}/extras")->assertJsonPath('data.doubt_session.notes', 'Google Meet link to follow');
});

it('keeps simulator tasks pending until the integration is configured, then staff retry', function () {
    config(['services.simulator.key' => null]);
    $course = editionCourse();
    $buyer = makeUser();
    buyEdition($buyer, $course, edition($course, 'Simulator Mastery'));

    $task = FulfilmentTask::query()->where('type', TaskType::SimulatorAccess->value)->sole();
    app()->call([new ProvisionSimulatorAccess($task), 'handle']);
    expect($task->refresh()->status)->toBe(TaskStatus::Pending)->and($task->last_error)->toContain('not configured');

    config(['services.simulator.key' => 'sim_key']);
    $this->actingAs(makeStaff(Permission::ManageFulfilment))->postJson("/api/v1/admin/fulfilment/{$task->uuid}/retry")->assertOk()->assertJsonPath('data.last_error', null);
    app()->call([new ProvisionSimulatorAccess($task), 'handle']);
    expect($task->refresh()->status)->toBe(TaskStatus::Done);
});

it('stops a video after the edition’s full views are used', function () {
    $course = editionCourse();
    $buyer = makeUser();
    buyEdition($buyer, $course, edition($course, 'Video Course'));
    $lecture = $course->lectures()->orderBy('position')->first(); // 420 s

    $this->actingAs($buyer)->postJson("/api/v1/lectures/{$lecture->id}/playback")->assertOk()->assertJsonPath('data.views_left', 1);
    // Watching through once, in steady progress reports.
    foreach (range(15, 420, 15) as $position) {
        $last = $this->actingAs($buyer)->putJson("/api/v1/lectures/{$lecture->id}/progress", ['position_seconds' => $position])->assertOk();
    }
    expect($last->json('data.views_left'))->toEqual(0);
    $this->actingAs($buyer)->postJson("/api/v1/lectures/{$lecture->id}/playback")->assertForbidden()->assertJsonPath('error.code', 'WATCH_LIMIT_REACHED');

    // Seeking ahead doesn't use up views.
    $other = $course->lectures()->orderBy('position')->skip(1)->first();
    $this->actingAs($buyer)->putJson("/api/v1/lectures/{$other->id}/progress", ['position_seconds' => 400])->assertOk()->assertJsonPath('data.views_left', 1);
});

it('counts progress only over the edition’s lectures, so the entry edition can still finish', function () {
    $course = editionCourse();
    $buyer = makeUser();
    buyEdition($buyer, $course, edition($course, 'Video Course'));

    foreach ($course->lectures()->orderBy('position')->take(4)->get() as $lecture) {
        $this->actingAs($buyer)->postJson("/api/v1/lectures/{$lecture->id}/completion")->assertOk();
    }

    expect(Enrollment::query()->where('user_id', $buyer->id)->value('progress_percent'))->toBe(100);
});

it('ends access after the period, and buying again restores it', function () {
    $course = editionCourse();
    $buyer = makeUser();
    buyEdition($buyer, $course, edition($course, 'Video Course'));

    $this->travel(366)->days();
    $this->actingAs($buyer)->getJson("/api/v1/learn/{$course->slug}")->assertForbidden()->assertJsonPath('error.code', 'ACCESS_EXPIRED');

    buyEdition($buyer, $course, edition($course, 'Video Course'));
    $this->actingAs($buyer)->getJson("/api/v1/learn/{$course->slug}")->assertOk();
    expect(Enrollment::query()->where('user_id', $buyer->id)->count())->toBe(1);
});

it('puts the learner back on their old edition when an upgrade is refunded', function () {
    $course = editionCourse();
    $buyer = makeUser();
    buyEdition($buyer, $course, edition($course, 'Video Course'));
    $upgrade = buyEdition($buyer, $course, edition($course, 'Course + Kit'));

    $this->actingAs(makeStaff(Permission::ManageFinance))
        ->postJson('/api/v1/admin/order-items/'.$upgrade->items()->first()->id.'/refund', ['reason' => 'Upgraded by mistake, asked same day'])
        ->assertCreated();

    $enrollment = Enrollment::query()->where('user_id', $buyer->id)->sole();
    expect($enrollment->isActive())->toBeTrue()->and($enrollment->edition_id)->toBe(edition($course, 'Video Course')->id);
});

it('manages editions in the builder; extras are for Erudsway staff only', function () {
    $course = editionCourse();
    $instructor = $course->instructor;
    $tier = PriceTier::query()->firstOrCreate(['currency' => 'INR', 'amount' => 1210000])->id;

    $this->actingAs($instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/editions", ['name' => 'Practice Vault', 'price_tier_id' => $tier, 'includes' => ['Workings'], 'video_view_limit' => 2])
        ->assertOk()->assertJsonCount(4, 'data.editions')->assertJsonPath('data.editions.3.name', 'Practice Vault');
    $this->actingAs($instructor)->postJson("/api/v1/instructor/courses/{$course->uuid}/editions", ['name' => 'Sneaky', 'price_tier_id' => $tier, 'grants_simulator' => true])
        ->assertForbidden()->assertJsonPath('error.code', 'STAFF_ONLY');

    // Make the vault the recommended one: only one can be.
    $vault = edition($course, 'Practice Vault');
    $this->actingAs($instructor)->patchJson("/api/v1/instructor/editions/{$vault->id}", ['is_recommended' => true])->assertOk();
    expect($course->editions()->where('is_recommended', true)->pluck('name')->all())->toBe(['Practice Vault']);

    // Reorder: vault before Simulator Mastery.
    $ids = $course->editions()->pluck('id')->all();
    [$a, $b, $c, $d] = $ids;
    $this->actingAs($instructor)->putJson("/api/v1/instructor/courses/{$course->uuid}/editions/order", ['ids' => [$a, $b, $d, $c]])->assertOk();
    expect($course->editions()->pluck('name')->all())->toBe(['Video Course', 'Course + Kit', 'Practice Vault', 'Simulator Mastery']);

    // Hold a lecture back to the vault; delete an edition nobody has bought.
    $lecture = $course->lectures()->first();
    $this->actingAs($instructor)->patchJson("/api/v1/instructor/lectures/{$lecture->id}", ['min_edition_id' => $vault->id])->assertOk();
    expect($lecture->fresh()->min_edition_id)->toBe($vault->id);
    $this->actingAs($instructor)->deleteJson("/api/v1/instructor/editions/{$vault->id}")->assertOk()->assertJsonCount(3, 'data.editions');
    expect($lecture->fresh()->min_edition_id)->toBeNull();

    // A bought edition can't be deleted; the course price follows the cheapest edition.
    buyEdition(makeUser(), $course, edition($course, 'Video Course'));
    $this->actingAs($instructor)->deleteJson('/api/v1/instructor/editions/'.edition($course, 'Video Course')->id)->assertStatus(409);
    expect($course->fresh()->priceTier->amount)->toBe(780000);
});
