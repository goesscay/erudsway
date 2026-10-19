<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "View as" for support. An admin signs in as a learner or instructor to see exactly what
 * they see; while impersonating, every write is refused (ReadOnlyWhileImpersonating), and
 * staff accounts can't be impersonated. Start and stop are audited.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public function __construct(private readonly AuditLogger $audit) {}

    public function start(Request $request, User $admin, User $target): void
    {
        if (! $admin->hasRole(Role::Admin->value) || ! $admin->can(Permission::ManageUsers->value)) {
            throw new DomainException('FORBIDDEN', 'Only admins can view the site as another user.', 403);
        }
        if ($this->impersonatorId($request) !== null) {
            throw new DomainException('ALREADY_IMPERSONATING', 'Stop viewing as the current user first.', 409);
        }
        if ($target->is($admin)) {
            throw new DomainException('CANNOT_IMPERSONATE', 'That’s you.', 422);
        }
        if ($target->getAllPermissions()->isNotEmpty()) {
            throw new DomainException('CANNOT_IMPERSONATE', 'Staff accounts can’t be impersonated.', 422);
        }
        if ($target->isSuspended()) {
            throw new DomainException('CANNOT_IMPERSONATE', 'This account is suspended.', 422);
        }

        $this->audit->record('user.impersonation_started', $target, null, null, $admin);
        $request->session()->regenerate();
        Auth::guard('web')->login($target);
        $this->resolveAs($request, $target);
        $request->session()->put(self::SESSION_KEY, $admin->id);
    }

    /** Back to the admin's own account. Returns the admin. */
    public function stop(Request $request): User
    {
        $adminId = $this->impersonatorId($request);
        $admin = $adminId ? User::query()->find($adminId) : null;
        if ($admin === null) {
            throw new DomainException('NOT_IMPERSONATING', 'You aren’t viewing as anyone.', 409);
        }

        /** @var User $target */
        $target = $request->user();
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();
        Auth::guard('web')->login($admin);
        $this->resolveAs($request, $admin);
        $this->audit->record('user.impersonation_stopped', $target, null, null, $admin);

        return $admin;
    }

    /** Make the rest of this request (and the API guard) see the newly signed-in user. */
    private function resolveAs(Request $request, User $user): void
    {
        Auth::guard('sanctum')->setUser($user);
        $request->setUserResolver(fn () => $user);
    }

    public function impersonatorId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }
        $id = $request->session()->get(self::SESSION_KEY);

        return is_numeric($id) ? (int) $id : null;
    }
}
