<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The highest progress ever reached. Un-ticking lectures lowers progress_percent but
        // never this, so it can't be used to get under the automatic-refund threshold.
        Schema::table('enrollments', function (Blueprint $table) {
            $table->unsignedTinyInteger('peak_progress_percent')->default(0);
        });
        DB::table('enrollments')->update(['peak_progress_percent' => DB::raw('progress_percent')]);
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('peak_progress_percent');
        });
    }
};
