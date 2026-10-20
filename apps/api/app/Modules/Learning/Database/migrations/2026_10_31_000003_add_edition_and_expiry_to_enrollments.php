<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An enrollment's edition (null = the whole course, as for grants and courses without
 * editions), when access ends (null = no end), and how much of each video has been watched
 * (for editions with a view limit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('edition_id')->nullable()->after('course_id')->constrained('course_editions')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->after('granted_at')->index();
        });
        Schema::table('lecture_progress', function (Blueprint $table) {
            $table->unsignedInteger('watched_seconds')->default(0)->after('max_position_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('lecture_progress', function (Blueprint $table) {
            $table->dropColumn('watched_seconds');
        });
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
            $table->dropConstrainedForeignId('edition_id');
        });
    }
};
