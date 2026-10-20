<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom staff roles get a description; accounts that staff create get their own
 * set-your-password tokens, which last longer than a password reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description', 300)->nullable()->after('name');
        });

        Schema::create('account_invite_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invite_tokens');
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
