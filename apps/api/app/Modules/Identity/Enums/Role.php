<?php

namespace App\Modules\Identity\Enums;

/**
 * The built-in roles. Super admins can also create custom staff roles (any other role name),
 * each a named set of permissions.
 */
enum Role: string
{
    case Student = 'student';
    case Instructor = 'instructor';
    case Admin = 'admin';
    /**
     * Held together with Admin. The only role that can make, remove or change admins
     * (including other super admins) and manage custom roles; otherwise the same as an admin.
     */
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Learner',
            self::Instructor => 'Instructor',
            self::Admin => 'Administrator',
            self::SuperAdmin => 'Super admin',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Student => 'Everyone who signs up. Buys courses and learns.',
            self::Instructor => 'Approved teachers. Build and sell their own courses.',
            self::Admin => 'Every permission, plus giving staff roles and permissions and viewing as users.',
            self::SuperAdmin => 'Everything an admin can do, plus managing admins and custom roles.',
        };
    }

    /** Whether a role name is one of these (case-insensitive), so it can't be reused or edited. */
    public static function isBuiltIn(string $name): bool
    {
        return self::tryFrom(mb_strtolower(trim($name))) !== null
            || in_array(mb_strtolower(trim($name)), ['super admin', 'administrator', 'learner'], true);
    }
}
