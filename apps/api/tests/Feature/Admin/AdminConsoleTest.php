<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Commerce\Support\Gst;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\Settings;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $gst = Category::query()->create(['slug' => 'gst', 'name' => 'GST']);
    Category::query()->create(['slug' => 'registration-returns', 'name' => 'Registration & Returns', 'parent_id' => $gst->id]);
});

describe('settings', function () {
    it('lists editable settings for settings managers only', function () {
        $this->actingAs(makeStaff(Permission::ManageFinance))->getJson('/api/v1/admin/settings')->assertForbidden();

        $rows = collect($this->actingAs(makeStaff(Permission::ManageSettings))->getJson('/api/v1/admin/settings')->assertOk()->json('data.items'))->keyBy('key');
        expect($rows['commerce.gst.rate_bps'])->toMatchArray(['value' => 1800, 'default' => 1800, 'overridden' => false, 'type' => 'bps'])
            ->and($rows->keys())->toContain('finance.tds.rate_bps', 'commerce.refunds.window_days', 'commerce.seller.gstin');
    });

    it('validates, applies at once, audits, survives a restart and resets to the default', function () {
        $staff = makeStaff(Permission::ManageSettings);
        $url = '/api/v1/admin/settings';

        $this->actingAs($staff)->putJson($url, ['values' => ['commerce.gst.rate_bps' => 5000, 'commerce.invoice_prefix' => 'erudsway']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.commerce.gst.rate_bps', 'values.commerce.invoice_prefix'], 'error.fields');
        $this->actingAs($staff)->putJson($url, ['values' => ['commerce.gst.sac' => '999293', 'app.debug' => true]])->assertJsonPath('error.code', 'UNKNOWN_SETTING');
        $this->actingAs($staff)->putJson($url, ['values' => ['commerce.seller.gstin' => '33AAAAA0000A1Z0']])->assertUnprocessable();
        expect(config('commerce.gst.rate_bps'))->toBe(1800);

        $this->actingAs($staff)->putJson($url, ['values' => ['commerce.gst.rate_bps' => '1200', 'finance.maker_checker' => false]])->assertOk();
        expect(config('commerce.gst.rate_bps'))->toBe(1200)
            ->and(Gst::split(11200))->toBe([10000, 1200])
            ->and(AuditLog::query()->where('action', 'settings.updated')->sole()->after)->toBe(['commerce.gst.rate_bps' => 1200, 'finance.maker_checker' => false]);

        // A fresh boot reads the override from the database.
        config()->set('commerce.gst.rate_bps', 1800);
        Cache::flush();
        app(Settings::class)->apply();
        expect(config('commerce.gst.rate_bps'))->toBe(1200);

        $this->actingAs($staff)->putJson($url, ['values' => ['commerce.gst.rate_bps' => null]])->assertOk();
        expect(config('commerce.gst.rate_bps'))->toBe(1800);
    });
});

describe('audit log', function () {
    it('can be searched and exported by auditors only', function () {
        $staff = makeStaff(Permission::ManageSettings);
        $this->actingAs($staff)->putJson('/api/v1/admin/settings', ['values' => ['commerce.refunds.window_days' => 14]])->assertOk();
        $this->actingAs(makeStaff(Permission::ManageSettings))->getJson('/api/v1/admin/audit-logs')->assertForbidden();

        $auditor = makeStaff(Permission::ViewAuditLogs);
        $this->actingAs($auditor)->getJson('/api/v1/admin/audit-logs?action=settings')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor.email', $staff->email)
            ->assertJsonPath('data.0.after', ['commerce.refunds.window_days' => 14]);
        $this->actingAs($auditor)->getJson('/api/v1/admin/audit-logs?action=user.')->assertJsonCount(0, 'data');
        $this->actingAs($auditor)->getJson('/api/v1/admin/audit-logs/actions')->assertJsonPath('data.items', ['settings.updated']);

        $csv = $this->actingAs($auditor)->get('/api/v1/admin/audit-logs/csv')->assertOk()->streamedContent();
        expect($csv)->toContain('settings.updated')->toContain($staff->email);
    });
});

describe('analytics', function () {
    beforeEach(fn () => useRazorpay());
    it('summarises sales, learners and work waiting', function () {
        $order = paidOrder();
        $reporter = makeStaff(Permission::ViewReports);
        $this->actingAs(makeStaff(Permission::ManageUsers))->getJson('/api/v1/admin/analytics/overview')->assertForbidden();

        $data = $this->actingAs($reporter)->getJson('/api/v1/admin/analytics/overview')->assertOk()->json('data');
        $kpis = collect($data['kpis'])->keyBy('key');
        expect($kpis['revenue']['value'])->toBe($order->total)
            ->and($kpis['orders']['value'])->toBe(1)
            ->and($data['daily'])->toHaveCount(30)
            ->and(collect($data['daily'])->sum('revenue'))->toBe($order->total)
            ->and($data['top_courses'][0]['revenue'])->toBe($order->total);

        // Each person sees only the queues they can work.
        $finance = collect($this->actingAs(makeStaff(Permission::ManageFinance))->getJson('/api/v1/admin/queues')->json('data'))->pluck('key');
        expect($finance->all())->toBe(['refunds', 'payouts', 'payout_accounts']);
        $this->actingAs(makeUser())->getJson('/api/v1/admin/queues')->assertForbidden();
    });

    it('builds every report and exports it as CSV in rupees', function () {
        $order = paidOrder();
        $reporter = makeStaff(Permission::ViewReports);

        foreach (['sales', 'courses', 'instructors', 'refunds'] as $name) {
            $this->actingAs($reporter)->getJson("/api/v1/admin/reports/{$name}")->assertOk()->assertJsonStructure(['data' => ['columns', 'rows', 'period']]);
        }
        $sales = $this->actingAs($reporter)->getJson('/api/v1/admin/reports/sales?group=month')->json('data');
        expect(collect($sales['rows'])->sum('collected'))->toBe($order->total);

        $courses = $this->actingAs($reporter)->getJson('/api/v1/admin/reports/courses')->json('data.rows');
        expect(collect($courses)->firstWhere('sales', 1))->not->toBeNull();

        $csv = $this->actingAs($reporter)->get('/api/v1/admin/reports/sales/csv?group=month')->assertOk()->streamedContent();
        expect($csv)->toContain('Collected (INR)')->toContain(number_format($order->total / 100, 2, '.', ''));
        $this->actingAs($reporter)->getJson('/api/v1/admin/reports/payroll')->assertNotFound();
    });
});

describe('users', function () {
    it('shows one user in depth to user managers', function () {
        useRazorpay();
        $order = paidOrder();
        $this->actingAs(makeStaff(Permission::ManageUsers))->getJson("/api/v1/admin/users/{$order->user->uuid}")->assertOk()
            ->assertJsonPath('data.email', $order->user->email)
            ->assertJsonPath('data.stats.orders', 1)
            ->assertJsonPath('data.stats.spent', $order->total)
            ->assertJsonPath('data.can_impersonate', true);
    });

    it('lets only admins change staff access, never their own, and keeps one admin', function () {
        $admin = makeUser(Role::Admin);
        $target = makeUser();
        $url = "/api/v1/admin/users/{$target->uuid}/access";

        $this->actingAs(makeStaff(Permission::ManageUsers))->putJson($url, ['admin' => false, 'permissions' => ['view_reports']])->assertForbidden();
        $this->actingAs($admin)->putJson($url, ['admin' => false, 'permissions' => ['view_reports', 'nonsense']])->assertUnprocessable();
        $this->actingAs($admin)->putJson($url, ['admin' => false, 'permissions' => ['view_reports', 'view_audit_logs']])->assertOk()
            ->assertJsonPath('data.direct_permissions', ['view_audit_logs', 'view_reports']);
        expect($target->fresh()->can('view_reports'))->toBeTrue()
            ->and(AuditLog::query()->where('action', 'user.access_changed')->sole()->after)->toBe(['admin' => false, 'permissions' => ['view_audit_logs', 'view_reports']]);

        $this->actingAs($admin)->putJson("/api/v1/admin/users/{$admin->uuid}/access", ['admin' => false, 'permissions' => []])->assertJsonPath('error.code', 'OWN_ACCESS');
        $this->actingAs($admin)->putJson($url, ['admin' => true, 'permissions' => []])->assertOk()->assertJsonPath('data.is_admin', true);
        $this->actingAs($target->fresh())->putJson("/api/v1/admin/users/{$admin->uuid}/access", ['admin' => false, 'permissions' => []])->assertOk();
        $this->actingAs($admin->fresh())->putJson($url, ['admin' => false, 'permissions' => []])->assertForbidden(); // no longer an admin
        $this->actingAs($target->fresh())->putJson("/api/v1/admin/users/{$target->uuid}/access", ['admin' => false, 'permissions' => []])->assertJsonPath('error.code', 'OWN_ACCESS');
    });
});

describe('impersonation', function () {
    it('lets an admin view the site as a learner, read-only, and come back', function () {
        $admin = makeUser(Role::Admin);
        $learner = makeUser();
        $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();

        $this->withHeaders(spaHeaders())->postJson("/api/v1/admin/users/{$learner->uuid}/impersonate")->assertOk()->assertJsonPath('data.email', $learner->email);
        $this->withHeaders(spaHeaders())->getJson('/api/v1/me')->assertJsonPath('data.email', $learner->email)->assertJsonPath('data.impersonator.name', $admin->name);

        // Looking works; changing anything doesn't.
        $this->withHeaders(spaHeaders())->getJson('/api/v1/me/enrollments')->assertOk();
        $this->withHeaders(spaHeaders())->patchJson('/api/v1/me', ['name' => 'Hacked'])->assertForbidden()->assertJsonPath('error.code', 'IMPERSONATING');
        $this->withHeaders(spaHeaders())->postJson('/api/v1/cart/items', ['course' => 'x'])->assertJsonPath('error.code', 'IMPERSONATING');
        expect($learner->fresh()->name)->not->toBe('Hacked');

        $this->withHeaders(spaHeaders())->postJson('/api/v1/auth/impersonation/stop')->assertOk()->assertJsonPath('data.email', $admin->email);
        $this->withHeaders(spaHeaders())->getJson('/api/v1/me')->assertJsonPath('data.email', $admin->email)->assertJsonPath('data.impersonator', null);
        expect(AuditLog::query()->pluck('action')->all())->toContain('user.impersonation_started', 'user.impersonation_stopped');
    });

    it('refuses non-admins, staff targets and suspended accounts', function () {
        $admin = makeUser(Role::Admin);
        $this->actingAs(makeStaff(Permission::ManageUsers))->postJson('/api/v1/admin/users/'.makeUser()->uuid.'/impersonate')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/admin/users/'.makeStaff(Permission::ViewReports)->uuid.'/impersonate')->assertJsonPath('error.code', 'CANNOT_IMPERSONATE');
        $suspended = makeUser();
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($admin)->postJson("/api/v1/admin/users/{$suspended->uuid}/impersonate")->assertJsonPath('error.code', 'CANNOT_IMPERSONATE');
    });
});
