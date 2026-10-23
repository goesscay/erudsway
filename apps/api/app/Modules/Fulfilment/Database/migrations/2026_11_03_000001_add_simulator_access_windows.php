<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simulator logins now end: a 7-day trial, or a paid plan's access period. The login is
 * removed from the simulator when its window closes (with a reminder a few days before).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfilment_tasks', function (Blueprint $table) {
            $table->timestamp('access_ends_at')->nullable()->after('completed_at');
            $table->timestamp('reminded_at')->nullable()->after('access_ends_at');
            $table->timestamp('removed_at')->nullable()->after('reminded_at');
            $table->index(['type', 'status', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('fulfilment_tasks', function (Blueprint $table) {
            $table->dropIndex(['type', 'status', 'removed_at']);
            $table->dropColumn(['access_ends_at', 'reminded_at', 'removed_at']);
        });
    }
};
