<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Http\Resources\AdminUserResource;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * User management. Requires the `manage_users` permission.
 */
class UserController extends Controller
{
    /** Search users by name or email, optionally filtered by role or status. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', Rule::enum(Role::class)],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($validated['q'] ?? null, function (Builder $query, string $q) {
                $term = '%'.mb_strtolower($q).'%';
                $query->where(fn (Builder $w) => $w->whereRaw('lower(name) like ?', [$term])->orWhere('email', 'like', $term));
            })
            ->when($validated['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return AdminUserResource::collection($users);
    }

    /** Block a user from signing in or using the API. */
    public function suspend(Request $request, User $user, AuditLogger $audit): AdminUserResource
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        if ($user->is($request->user())) {
            throw new DomainException('CANNOT_SUSPEND_SELF', 'You cannot suspend your own account.', 422);
        }
        if ($user->hasRole(Role::Admin->value)) {
            throw new DomainException('CANNOT_SUSPEND_ADMIN', 'Remove the admin role before suspending this account.', 422);
        }

        return $this->setStatus($user, UserStatus::Suspended, 'user.suspended', $audit, $request->string('reason')->toString());
    }

    /** Restore a suspended user's access. */
    public function reactivate(User $user, AuditLogger $audit): AdminUserResource
    {
        return $this->setStatus($user, UserStatus::Active, 'user.reactivated', $audit);
    }

    private function setStatus(User $user, UserStatus $status, string $action, AuditLogger $audit, ?string $reason = null): AdminUserResource
    {
        $before = $user->status;

        DB::transaction(function () use ($user, $status, $action, $audit, $before, $reason) {
            $user->forceFill(['status' => $status])->save();
            $audit->record($action, $user, ['status' => $before->value], array_filter([
                'status' => $status->value,
                'reason' => $reason,
            ]));
        });

        return new AdminUserResource($user->load('roles'));
    }
}
