<?php

use App\Modules\Identity\Enums\Role as R;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Catalogue management: edit any course, change its owner or status, and run the category tree. */
return new class extends Migration
{
    private const NEW = ['manage_courses', 'manage_categories'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::NEW as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::findOrCreate(R::Admin->value, 'web')->givePermissionTo(self::NEW);
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::NEW)->delete();
    }
};
