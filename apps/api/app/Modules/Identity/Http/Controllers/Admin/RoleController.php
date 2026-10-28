<?php

namespace App\Modules\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Http\Requests\SaveRoleRequest;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Roles and what each permission lets staff do. Anyone with `manage_users` can look;
 * only super admins create, change or delete custom roles. Built-in roles are fixed.
 */
class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Every role (built-in first) with its permissions and how many people hold it. */
    public function index(): JsonResponse
    {
        $order = array_flip(array_map(fn (Role $r) => $r->value, Role::cases()));
        $roles = RoleModel::query()->where('guard_name', 'web')->with('permissions')->get()
            ->sortBy(fn (RoleModel $r) => [isset($order[$r->name]) ? 0 : 1, $order[$r->name] ?? 0, mb_strtolower($r->name)])
            ->values();

        $counts = $this->holderCounts();

        return ApiResponse::data([
            /** @var list<array{id: int, name: string, label: string, description: string|null, built_in: bool, permissions: list<string>, users_count: int}> */
            'items' => $roles->map(fn (RoleModel $r) => $this->roleData($r, $counts[$r->getKey()] ?? 0))->values()->all(),
        ]);
    }

    /** Super admins only: a new staff role made of the chosen permissions. */
    public function store(SaveRoleRequest $request): JsonResponse
    {
        $this->assertSuperAdmin($request);
        /** @var array{name: string, description: string|null, permissions: list<string>} $data */
        $data = $request->validated();
        $permissions = $this->normalise($data['permissions']);

        $role = DB::transaction(function () use ($data, $permissions) {
            $role = new RoleModel(['name' => $data['name'], 'guard_name' => 'web']);
            $role->forceFill(['description' => $data['description']])->save();
            $role->syncPermissions($permissions);

            return $role;
        });
        $this->audit->record('role.created', $role, null, ['name' => $role->name, 'permissions' => $permissions]);

        return ApiResponse::data($this->roleData($role->load('permissions'), 0), status: 201);
    }

    /** Super admins only: rename a custom role or change its permissions (applies to everyone who holds it). */
    public function update(SaveRoleRequest $request, RoleModel $role): JsonResponse
    {
        $this->assertSuperAdmin($request);
        $this->assertCustom($role);
        /** @var array{name: string, description: string|null, permissions: list<string>} $data */
        $data = $request->validated();
        $permissions = $this->normalise($data['permissions']);
        $before = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->sort()->values()->all()];

        DB::transaction(function () use ($role, $data, $permissions) {
            $role->forceFill(['name' => $data['name'], 'description' => $data['description']])->save();
            $role->syncPermissions($permissions);
        });
        $this->audit->record('role.updated', $role, $before, ['name' => $role->name, 'permissions' => $permissions]);

        return ApiResponse::data($this->roleData($role->load('permissions'), $this->holderCounts()[$role->getKey()] ?? 0));
    }

    /** Super admins only: delete a custom role. Everyone who held it loses its permissions. */
    public function destroy(Request $request, RoleModel $role): Response
    {
        $this->assertSuperAdmin($request);
        $this->assertCustom($role);
        $before = [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            'holders' => User::role($role->name)->pluck('id')->all(),
        ];

        $role->delete();
        $this->audit->record('role.deleted', $role, $before, null);

        return response()->noContent();
    }

    /** Every permission, what it allows, and how many people hold it (directly or through a role). */
    public function permissions(): JsonResponse
    {
        return ApiResponse::data([
            /** @var list<array{key: 'review_instructor_applications'|'moderate_courses'|'manage_courses'|'manage_categories'|'manage_fulfilment'|'manage_users'|'manage_finance'|'view_reports'|'manage_settings'|'view_audit_logs'|'handle_support', label: string, description: string, users_count: int}> */
            'items' => array_map(fn (Permission $p) => [
                'key' => $p->value,
                'label' => $p->label(),
                'description' => $p->description(),
                'users_count' => User::permission($p->value)->count(),
            ], Permission::cases()),
        ]);
    }

    /** Who holds a permission, and through what: given directly, or through which roles. */
    public function holders(Permission $permission): JsonResponse
    {
        $users = User::permission($permission->value)->with(['roles.permissions', 'permissions'])->orderBy('name')->limit(200)->get();

        return ApiResponse::data([
            /** @var list<array{id: string, name: string, email: string, status: 'active'|'suspended', via: list<string>}> */
            'items' => $users->map(fn (User $u) => [
                'id' => $u->uuid,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status->value,
                // "Direct" and/or the names of the roles that give it.
                'via' => array_merge(
                    $u->permissions->contains('name', $permission->value) ? ['Direct'] : [],
                    $u->roles->filter(fn (Model $r) => $r instanceof RoleModel && $r->permissions->contains('name', $permission->value))
                        ->map(fn (Model $r) => Role::tryFrom((string) $r->getAttribute('name'))?->label() ?? (string) $r->getAttribute('name'))->values()->all(),
                ),
            ])->values()->all(),
        ]);
    }

    /**
     * @return array{id: int, name: string, label: string, description: string|null, built_in: bool, permissions: list<string>, users_count: int}
     */
    private function roleData(RoleModel $role, int $holders): array
    {
        $builtIn = Role::tryFrom($role->name);
        // Admins and super admins hold every permission.
        $all = in_array($builtIn, [Role::Admin, Role::SuperAdmin], true);

        return [
            'id' => (int) $role->getKey(),
            'name' => $role->name,
            'label' => $builtIn?->label() ?? $role->name,
            'description' => $builtIn?->description() ?? $role->getAttribute('description'),
            'built_in' => $builtIn !== null,
            'permissions' => $all
                ? array_map(fn (Permission $p) => $p->value, Permission::cases())
                : $role->permissions->pluck('name')->sort()->values()->all(),
            'users_count' => $holders,
        ];
    }

    /** @return array<int, int> role id => number of users holding it */
    private function holderCounts(): array
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', (new User)->getMorphClass())
            ->selectRaw('role_id, count(*) as holders')->groupBy('role_id')
            ->pluck('holders', 'role_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function normalise(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }

    private function assertSuperAdmin(Request $request): void
    {
        /** @var User $actor */
        $actor = $request->user();
        if (! $actor->hasRole(Role::SuperAdmin->value)) {
            throw new DomainException('SUPER_ADMIN_ONLY', 'Only a super admin can create, change or delete roles.', 403);
        }
    }

    private function assertCustom(RoleModel $role): void
    {
        if (Role::tryFrom($role->name) !== null) {
            throw new DomainException('BUILT_IN_ROLE', 'Built-in roles can’t be changed or deleted.', 422);
        }
    }
}
