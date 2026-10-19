<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Courses an admin pins to the top of the homepage and category pages (newest pin first). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('featured_at')->nullable()->after('published_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['featured_at']);
            $table->dropColumn('featured_at');
        });
    }
};
