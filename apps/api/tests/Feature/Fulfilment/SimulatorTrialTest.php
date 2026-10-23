<?php

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Finance\Services\CommissionRules;
use App\Modules\Fulfilment\Enums\TaskStatus;
use App\Modules\Fulfilment\Enums\TaskType;
use App\Modules\Fulfilment\Models\FulfilmentTask;
use App\Modules\Fulfilment\Notifications\SimulatorAccessEnded;
use App\Modules\Fulfilment\Notifications\SimulatorAccessEnding;
use App\Modules\Fulfilment\Notifications\SimulatorTrialReady;
use App\Modules\Identity\Enums\Role;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
    config(['services.simulator.url' => 'https://sim.test', 'services.simulator.key' => 'sim_key']);
    $this->nextSimulatorId = 100;
    Http::fake(function (Request $request) {
        if (parse_url($request->url(), PHP_URL_HOST) !== 'sim.test') {
            return null;
        }
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        return match (true) {
            $request->method() === 'POST' && $path === '/api/integration/v1/gst/users' => Http::response(['id' => ++$this->nextSimulatorId, 'username' => $request['username']], 201),
            $request->method() === 'DELETE' && str_starts_with($path, '/api/integration/v1/gst/users/') => Http::response(['ok' => true]),
            $path === '/api/integration/v1/sso' => Http::response(['url' => 'https://sim.test/sso?code=abc', 'expires_in' => 60]),
            default => Http::response([], 404),
        };
    });
    makeUser(Role::Admin)->assignRole(Role::SuperAdmin->value);
    $this->artisan('simulator:setup-plans')->assertSuccessful();
});

function simulatorCalls(string $method, string $pathStart): int
{
    return Http::recorded(fn (Request $r) => $r->method() === $method && str_contains($r->url(), $pathStart))->count();
}

function plansCourse(): Course
{
    return Course::query()->where('slug', 'gst-simulator')->with('editions')->firstOrFail();
}

/** Record a paid plan the way finance staff would (same pipeline as an online purchase). */
function buyPlan(User $learner, int $position, ?int $amount = null): void
{
    $course = plansCourse();
    $edition = $course->editions->firstWhere('position', $position);
    test()->actingAs(makeUser(Role::Admin))->postJson('/api/v1/admin/offline-payments', [
        'user' => $learner->uuid, 'course' => $course->slug, 'edition_id' => $edition->id,
        'amount' => $amount ?? $edition->price(), 'method' => 'upi', 'received_on' => now()->toDateString(),
        'name' => $learner->name, 'state_code' => '33',
    ])->assertCreated();
}

it('sets up the simulator plans once, sold by the platform with no instructor share', function () {
    $course = plansCourse();
    expect($course->status->value)->toBe('published')
        ->and($course->editions->pluck('name')->all())->toBe(['3 months', '12 months'])
        ->and($course->editions->pluck('access_days')->all())->toBe([90, 365])
        ->and($course->editions->every(fn ($e) => $e->grants_simulator))->toBeTrue()
        ->and($course->lecture_count)->toBe(1);
    foreach (SaleChannel::cases() as $channel) {
        expect(app(CommissionRules::class)->rateFor($channel, $course->instructor_id))->toBe(0);
    }

    $this->artisan('simulator:setup-plans')->assertSuccessful();
    expect(Course::query()->where('slug', 'gst-simulator')->count())->toBe(1);

    $this->getJson('/api/v1/simulator')->assertOk()
        ->assertJsonPath('data.trial_days', 7)
        ->assertJsonPath('data.plans_course', 'gst-simulator')
        ->assertJsonPath('data.login_url', 'https://sim.test/gst/login');
});

it('starts a 7-day trial once per verified account and signs the learner in', function () {
    $this->actingAs(makeUser(verified: false))->postJson('/api/v1/me/simulator/trial')
        ->assertForbidden()->assertJsonPath('error.code', 'EMAIL_UNVERIFIED');

    $learner = makeUser();
    $this->actingAs($learner)->getJson('/api/v1/me/simulator')->assertOk()
        ->assertJsonPath('data.state', 'none')->assertJsonPath('data.can_start_trial', true);

    $this->actingAs($learner)->postJson('/api/v1/me/simulator/trial')->assertCreated()
        ->assertJsonPath('data.state', 'trial')
        ->assertJsonPath('data.can_start_trial', false);

    $task = FulfilmentTask::query()->where('user_id', $learner->id)->sole();
    expect($task->type)->toBe(TaskType::SimulatorTrial)
        ->and($task->access_ends_at->diffInHours(now()->addDays(7)))->toBeLessThan(1)
        ->and($task->data['username'])->toStartWith('erw');
    Notification::assertSentTo($learner, SimulatorTrialReady::class);

    $this->actingAs($learner)->postJson('/api/v1/me/simulator/open')->assertOk()->assertJsonPath('data.url', 'https://sim.test/sso?code=abc');
    $this->actingAs($learner)->postJson('/api/v1/me/simulator/trial')->assertStatus(409)->assertJsonPath('error.code', 'TRIAL_USED');
});

it('reminds near the end, then removes the trial login from the simulator', function () {
    $learner = makeUser();
    $this->actingAs($learner)->postJson('/api/v1/me/simulator/trial')->assertCreated();

    $this->travel(5 * 24 + 1)->hours();
    $this->artisan('simulator:sweep')->assertSuccessful();
    Notification::assertSentTo($learner, SimulatorAccessEnding::class);
    expect(simulatorCalls('DELETE', '/gst/users/'))->toBe(0);

    $this->travel(2)->days();
    $this->artisan('simulator:sweep')->assertSuccessful();
    expect(simulatorCalls('DELETE', '/gst/users/101'))->toBe(1);
    Notification::assertSentTo($learner, SimulatorAccessEnded::class);

    $task = FulfilmentTask::query()->where('user_id', $learner->id)->sole();
    expect($task->removed_at)->not->toBeNull()->and($task->data)->not->toHaveKey('password');

    $this->actingAs($learner)->getJson('/api/v1/me/simulator')->assertOk()
        ->assertJsonPath('data.state', 'trial_ended')->assertJsonPath('data.can_start_trial', false)->assertJsonPath('data.login', null);
    $this->actingAs($learner)->postJson('/api/v1/me/simulator/open')->assertStatus(409);
});

it('keeps the trial login when the learner buys a plan', function () {
    $learner = makeUser();
    $this->actingAs($learner)->postJson('/api/v1/me/simulator/trial')->assertCreated();
    $trialUsername = FulfilmentTask::query()->where('user_id', $learner->id)->sole()->data['username'];

    buyPlan($learner, 1);

    expect(simulatorCalls('POST', '/gst/users'))->toBe(1);
    $paid = FulfilmentTask::query()->where('user_id', $learner->id)->where('type', TaskType::SimulatorAccess->value)->sole();
    expect($paid->data['username'])->toBe($trialUsername)
        ->and($paid->access_ends_at->diffInHours(now()->addDays(90)))->toBeLessThan(1)
        ->and(FulfilmentTask::query()->where('type', TaskType::SimulatorTrial->value)->sole()->status)->toBe(TaskStatus::Cancelled);

    $this->actingAs($learner)->getJson('/api/v1/me/simulator')->assertOk()
        ->assertJsonPath('data.state', 'paid')->assertJsonPath('data.login.username', $trialUsername);

    // The trial's end date passes: the paid login stays.
    $this->travel(8)->days();
    $this->artisan('simulator:sweep')->assertSuccessful();
    expect(simulatorCalls('DELETE', '/gst/users/'))->toBe(0);
});

it('renews before the end from the current end date, and recreates a removed login on a new purchase', function () {
    $learner = makeUser();
    buyPlan($learner, 1);
    $enrollment = Enrollment::query()->where('user_id', $learner->id)->sole();
    $firstEnd = $enrollment->expires_at;

    // Too early to renew.
    $quote = fn () => $this->actingAs(makeUser(Role::Admin))->getJson('/api/v1/admin/offline-payments/quote?'.http_build_query([
        'user' => $learner->uuid, 'course' => 'gst-simulator', 'edition_id' => plansCourse()->editions->firstWhere('position', 1)->id,
    ]));
    $quote()->assertOk()->assertJsonPath('data.problem', 'You already have this edition or a higher one.');

    $this->travel(70)->days();
    $quote()->assertOk()->assertJsonPath('data.problem', null)->assertJsonPath('data.list_price', 199_900);
    buyPlan($learner, 1);
    expect($enrollment->fresh()->expires_at->diffInHours($firstEnd->copy()->addDays(90)))->toBeLessThan(1)
        ->and(simulatorCalls('POST', '/gst/users'))->toBe(1);

    // It runs out: the login goes, and buying again sets up a fresh one.
    $this->travel(120)->days();
    $this->artisan('simulator:sweep')->assertSuccessful();
    expect(simulatorCalls('DELETE', '/gst/users/101'))->toBe(1);

    buyPlan($learner, 2);
    expect(simulatorCalls('POST', '/gst/users'))->toBe(2);
    $this->actingAs($learner)->getJson('/api/v1/me/simulator')->assertOk()->assertJsonPath('data.state', 'paid');
});

it('won’t start a trial for someone who already has paid access', function () {
    $learner = makeUser();
    buyPlan($learner, 2);

    $this->actingAs($learner)->postJson('/api/v1/me/simulator/trial')->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_HAS_ACCESS');
});
