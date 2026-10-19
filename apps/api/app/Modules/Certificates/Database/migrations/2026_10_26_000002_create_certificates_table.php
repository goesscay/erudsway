<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One certificate per enrollment. What it says is snapshotted at issue, so later
        // renames of the learner, course or instructor don't change an issued certificate.
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('serial', 24)->unique();
            $table->string('code', 24)->unique(); // public verification code
            $table->string('recipient_name');
            $table->string('course_title');
            $table->string('instructor_name');
            $table->unsignedInteger('duration_seconds');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['user_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
