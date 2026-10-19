<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A password hash carried over from the old Moodle site (SHA-512 crypt). Checked once at
 * sign-in, then replaced by a normal hash and cleared — see LegacyPasswords.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('legacy_password_hash')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('legacy_password_hash');
        });
    }
};
