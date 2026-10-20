<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Http\Requests\StoreUserRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRequest;
use App\Modules\Identity\Http\Resources\AdminUserResource;
use App\Modules\Identity\Notifications\AccountInvite;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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
            // Any role name, built-in or custom.
            'role' => ['sometimes', 'string', Rule::exists('roles', 'name')],
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

    /**
     * Create an account for someone and email them a link to set their password. Only admins
     * can give custom roles at the same time.
     */
    public function store(StoreUserRequest $request, AuditLogger $audit): AdminUserResource
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var array{name: string, email: string, roles?: list<string>} $data */
        $data = $request->validated();
        $roles = array_values(array_unique($data['roles'] ?? []));
        if ($roles !== [] && ! $actor->hasRole(Role::Admin->value)) {
            throw new DomainException('FORBIDDEN', 'Only admins can give staff roles.', 403);
        }

        $user = DB::transaction(function () use ($data, $roles) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Str::random(64)]);
            $user->profile()->create();
            $user->assignRole([Role::Student->value, ...$roles]);

            return $user;
        });
        $audit->record('user.created', $user, null, ['name' => $user->name, 'email' => $user->email, 'roles' => $roles], $actor);
        $this->sendInvite($user);

        return new AdminUserResource($user->load('roles'));
    }

    /**
     * Correct someone's name or email. A new email must be verified again. Only a super admin
     * can change an admin's details.
     */
    public function update(UpdateUserRequest $request, User $user, AuditLogger $audit): AdminUserResource
    {
        /** @var User $actor */
        $actor = $request->user();
        if ($user->hasRole(Role::Admin->value) && ! $actor->is($user) && ! $actor->hasRole(Role::SuperAdmin->value)) {
            throw new DomainException('SUPER_ADMIN_ONLY', 'Only a super admin can change an admin’s details.', 403);
        }
        /** @var array{name?: string, email?: string} $data */
        $data = $request->validated();
        $before = $user->only(['name', 'email']);
        $emailChanged = isset($data['email']) && $data['email'] !== $user->email;

        $user->fill($data);
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }
        if ($user->isDirty()) {
            $user->save();
            $audit->record('user.details_changed', $user, $before, $user->only(['name', 'email']), $actor);
            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }
        }

        return new AdminUserResource($user->load('roles'));
    }

    /** Email the "set your password" link again, for someone who hasn't signed in yet. */
    public function invite(User $user, AuditLogger $audit): AdminUserResource
    {
        if ($user->last_login_at !== null) {
            throw new DomainException('ALREADY_SIGNED_IN', 'This person has already signed in. They can use “Forgot password” if they need to.', 422);
        }
        $this->sendInvite($user);
        $audit->record('user.invite_sent', $user, null, ['email' => $user->email]);

        return new AdminUserResource($user->load('roles'));
    }

    private function sendInvite(User $user): void
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker('invites');
        $user->notify(new AccountInvite($broker->createToken($user)));
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
