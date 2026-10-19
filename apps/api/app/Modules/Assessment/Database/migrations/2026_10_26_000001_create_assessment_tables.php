<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One quiz per quiz lecture. No time limit and unlimited attempts: the
        // quizzes are practice checks, and the lecture completes once a pass is reached.
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecture_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pass_percent')->default(70);
            $table->boolean('shuffle_questions')->default(false);
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // single | multiple
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->index(['quiz_id', 'position']);
        });

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->index(['question_id', 'position']);
        });

        // Submitted in one request and graded at once; the answers are kept for review.
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers'); // {question_id: [option_id, ...]}
            $table->unsignedSmallInteger('correct_count');
            $table->unsignedSmallInteger('question_count');
            $table->unsignedTinyInteger('score_percent');
            $table->boolean('passed');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['quiz_id', 'user_id', 'submitted_at']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecture_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('instructions_html')->nullable();
            $table->boolean('allow_file')->default(true);
            $table->timestamps();
        });

        // One submission per learner per assignment; resubmitting replaces it.
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 20)->default('submitted'); // submitted | reviewed
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['assignment_id', 'user_id']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('assignment_feedback')->default(true);
            $table->boolean('instructor_submissions')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn(['assignment_feedback', 'instructor_submissions']);
        });
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
