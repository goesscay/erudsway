<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Learners moved from the old Moodle site keep their passwords. Their Moodle hash sits in
 * `legacy_password_hash` (never in `password`, so nothing else ever has to understand it).
 * At sign-in, a matching password is re-saved as a normal hash and the legacy one is
 * cleared; after that the account is like any other.
 */
class LegacyPasswords
{
    /** Hash formats Moodle has used that PHP can verify: SHA-512 crypt and bcrypt. */
    private const FORMATS = ['$6$', '$2y$', '$2b$'];

    /** Upgrade the account if this is its first sign-in with a carried-over password. */
    public function upgrade(string $email, string $password): void
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->whereNotNull('legacy_password_hash')->first();
        if ($user === null) {
            return;
        }

        $hash = (string) DB::table('users')->where('id', $user->id)->value('legacy_password_hash');
        if (! Str::startsWith($hash, self::FORMATS) || ! password_verify($password, $hash)) {
            return;
        }

        $user->forceFill(['password' => $password])->save();
        self::clear($user);
    }

    /** Store a Moodle hash for an account (raw, bypassing the model's password cast). */
    public static function store(User $user, string $hash): void
    {
        DB::table('users')->where('id', $user->id)->update(['legacy_password_hash' => $hash]);
    }

    public static function clear(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update(['legacy_password_hash' => null]);
    }
}
