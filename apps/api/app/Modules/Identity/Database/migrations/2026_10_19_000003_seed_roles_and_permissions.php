<?php

use App\Modules\Identity\Enums\Permission as P;
use App\Modules\Identity\Enums\Role as R;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions are reference data every environment needs, so they ship
 * as a migration rather than a seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (P::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach (R::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        Role::findByName(R::Admin->value, 'web')->syncPermissions(array_map(fn (P $p) => $p->value, P::cases()));
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->whereIn('name', array_map(fn (R $r) => $r->value, R::cases()))->delete();
        Permission::query()->whereIn('name', array_map(fn (P $p) => $p->value, P::cases()))->delete();
    }
};
