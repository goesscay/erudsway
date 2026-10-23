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

        // Logins handed out before this get the end date of the access that pays for them.
        DB::table('fulfilment_tasks')->where('type', 'simulator_access')->whereNull('access_ends_at')->orderBy('id')->each(function (object $task) {
            $ends = DB::table('enrollments')->where('user_id', $task->user_id)->where('course_id', $task->course_id)->value('expires_at');
            if ($ends !== null) {
                DB::table('fulfilment_tasks')->where('id', $task->id)->update(['access_ends_at' => $ends]);
            }
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
