<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per learner and course; access is re-checked against it on every key request.
        // Course progress is cached on the row (the plan's course_progress table, folded in 1:1).
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('source', 20); // purchase | free | coupon | admin_grant
            $table->string('status', 20)->default('active')->index(); // active | refunded | revoked
            $table->unsignedBigInteger('order_item_id')->nullable(); // Phase 5
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->foreignId('last_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['user_id', 'status', 'last_accessed_at']);
        });

        Schema::create('lecture_progress', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->unsignedInteger('max_position_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->primary(['user_id', 'lecture_id']);
            $table->index(['user_id', 'course_id']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecture_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('at_seconds')->default(0);
            $table->text('body');
            $table->timestamps();

            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('lecture_progress');
        Schema::dropIfExists('enrollments');
    }
};
