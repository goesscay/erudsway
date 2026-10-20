<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Certificates\Models\Certificate;
use App\Modules\Commerce\Models\Order;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Services\Impersonation;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** One user in depth, staff access, and "view as". Requires `manage_users`. */
class UserAccessController extends Controller
{
    /** Everything support needs about one user. */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'permissions', 'instructorProfile', 'profile']);
        $orders = Order::query()->where('user_id', $user->id)->whereNotNull('paid_at');

        return ApiResponse::data([
            'id' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            /** @var 'active'|'suspended' */
            'status' => $user->status->value,
            'created_at' => $user->created_at->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            /** @var list<string> */
            'roles' => $user->getRoleNames()->values()->all(),
            'is_admin' => $user->hasRole(Role::Admin->value),
            'is_super_admin' => $user->hasRole(Role::SuperAdmin->value),
            // Staff permissions, whether given directly or through the admin role.
            /** @var list<string> */
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            /** @var list<string> */
            'direct_permissions' => $user->permissions->pluck('name')->values()->all(),
            /** @var array{display_name: string, slug: string|null}|null */
            'instructor' => $user->instructorProfile ? ['display_name' => $user->instructorProfile->display_name, 'slug' => $user->instructorProfile->slug] : null,
            'stats' => [
                'enrollments' => Enrollment::query()->where('user_id', $user->id)->count(),
                'completed' => Enrollment::query()->where('user_id', $user->id)->whereNotNull('completed_at')->count(),
                'certificates' => Certificate::query()->where('user_id', $user->id)->count(),
                'orders' => (clone $orders)->count(),
                'spent' => (int) (clone $orders)->sum('total'),
            ],
            /** @var list<array{number: string, total: int, status: string, paid_at: string|null}> */
            'recent_orders' => (clone $orders)->latest('paid_at')->limit(5)->get()->map(fn (Order $o) => [
                'number' => $o->number,
                'total' => $o->total,
                'status' => $o->status->value,
                'paid_at' => $o->paid_at?->toIso8601String(),
            ])->values()->all(),
            // Recent audited actions by or about this user.
            /** @var list<array{action: string, by: string|null, about_this_user: bool, created_at: string}> */
            'activity' => AuditLog::query()
                ->where(fn ($q) => $q->where('actor_id', $user->id)->orWhere(fn ($w) => $w->where('subject_type', $user->getMorphClass())->where('subject_id', (string) $user->id)))
                ->with('actor:id,name')->latest('id')->limit(15)->get()->map(fn (AuditLog $l) => [
                    'action' => $l->action,
                    'by' => $l->actor?->name,
                    'about_this_user' => $l->subject_type === $user->getMorphClass() && $l->subject_id === (string) $user->id,
                    'created_at' => $l->created_at->toIso8601String(),
                ])->values()->all(),
            'can_impersonate' => $user->getAllPermissions()->isEmpty() && ! $user->isSuspended(),
        ]);
    }

    /**
     * Give someone individual staff permissions (any admin), or make or remove an admin or a
     * super admin (super admins only — as is changing anything for an existing admin).
     * Nobody can change their own access; the last admin and last super admin stay.
     */
    public function updateAccess(Request $request, User $user, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'admin' => ['required', 'boolean'],
            'super_admin' => ['sometimes', 'boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ]);
        /** @var User $actor */
        $actor = $request->user();
        if (! $actor->hasRole(Role::Admin->value)) {
            throw new DomainException('FORBIDDEN', 'Only admins can change staff access.', 403);
        }
        if ($actor->is($user)) {
            throw new DomainException('OWN_ACCESS', 'You can’t change your own access. Ask another admin.', 422);
        }
        $wasAdmin = $user->hasRole(Role::Admin->value);
        $wasSuper = $user->hasRole(Role::SuperAdmin->value);
        // A super admin is always an admin too.
        $super = (bool) ($data['super_admin'] ?? $wasSuper);
        $admin = (bool) $data['admin'] || $super;
        if (($admin !== $wasAdmin || $super !== $wasSuper || $wasAdmin) && ! $actor->hasRole(Role::SuperAdmin->value)) {
            throw new DomainException('SUPER_ADMIN_ONLY', 'Only a super admin can make, remove or change an admin.', 403);
        }
        if ($wasAdmin && ! $admin && User::role(Role::Admin->value)->count() <= 1) {
            throw new DomainException('LAST_ADMIN', 'There must always be at least one admin.', 422);
        }
        if ($wasSuper && ! $super && User::role(Role::SuperAdmin->value)->count() <= 1) {
            throw new DomainException('LAST_SUPER_ADMIN', 'There must always be at least one super admin.', 422);
        }

        $before = ['admin' => $wasAdmin, 'super_admin' => $wasSuper, 'permissions' => $user->permissions->pluck('name')->sort()->values()->all()];
        $permissions = array_values(array_unique($data['permissions']));
        sort($permissions);

        DB::transaction(function () use ($user, $admin, $super, $permissions, $wasAdmin, $wasSuper) {
            if ($admin && ! $wasAdmin) {
                $user->assignRole(Role::Admin->value);
            } elseif (! $admin && $wasAdmin) {
                $user->removeRole(Role::Admin->value);
            }
            if ($super && ! $wasSuper) {
                $user->assignRole(Role::SuperAdmin->value);
            } elseif (! $super && $wasSuper) {
                $user->removeRole(Role::SuperAdmin->value);
            }
            $user->syncPermissions($permissions);
        });
        $audit->record('user.access_changed', $user, $before, ['admin' => $admin, 'super_admin' => $super, 'permissions' => $permissions], $actor);

        return $this->show($user->fresh() ?? $user);
    }

    /** Admin only: start viewing the site as this user (read-only). */
    public function impersonate(Request $request, User $user, Impersonation $impersonation): UserResource
    {
        /** @var User $admin */
        $admin = $request->user();
        $impersonation->start($request, $admin, $user);

        return new UserResource($user->load(['profile', 'instructorProfile']));
    }
}
