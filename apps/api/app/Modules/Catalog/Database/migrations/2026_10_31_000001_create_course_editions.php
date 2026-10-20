<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editions: one course sold at several levels (Video Course → Course + Kit → …), each
 * including everything below it. Lectures and downloads can be held back to an edition.
 * A course without editions is sold at its single price, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position'); // 1 = entry level; higher includes lower
            $table->string('name', 60);
            $table->foreignId('price_tier_id')->constrained('price_tiers')->restrictOnDelete();
            $table->json('includes'); // bullet points shown on the course page
            $table->boolean('is_recommended')->default(false);
            $table->unsignedSmallInteger('access_days')->nullable(); // null = no expiry
            $table->unsignedTinyInteger('video_view_limit')->nullable(); // full views per video; null = unlimited
            $table->boolean('grants_simulator')->default(false);
            $table->boolean('includes_doubt_session')->default(false);
            $table->timestamps();

            $table->unique(['course_id', 'position']);
        });

        Schema::table('lectures', function (Blueprint $table) {
            $table->foreignId('min_edition_id')->nullable()->after('is_free_preview')->constrained('course_editions')->nullOnDelete();
        });
        Schema::table('lecture_resources', function (Blueprint $table) {
            $table->foreignId('min_edition_id')->nullable()->constrained('course_editions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lecture_resources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('min_edition_id');
        });
        Schema::table('lectures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('min_edition_id');
        });
        Schema::dropIfExists('course_editions');
    }
};
