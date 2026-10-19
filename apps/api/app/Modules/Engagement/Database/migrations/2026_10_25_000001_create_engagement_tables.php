<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Engagement (PLAN §8): ratings and reviews, course Q&A, announcements, in-app
| notifications and email preferences. Learner-written text is stored as plain text and
| rendered as text, never HTML; announcements are rich text sanitised on save.
*/
return new class extends Migration
{
    public function up(): void
    {
        // Laravel's database notification channel (the in-app bell).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('course_announcements')->default(true);
            $table->boolean('qa_replies')->default(true);
            $table->boolean('instructor_questions')->default(true);
            $table->boolean('instructor_reviews')->default(true);
            $table->boolean('promotions')->default(false); // opt-in only
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->string('status', 20)->default('visible')->index(); // visible | flagged | hidden
            $table->unsignedInteger('report_count')->default(0);
            $table->text('instructor_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('moderation_note', 500)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'status', 'rating']);
        });

        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['review_id', 'user_id']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecture_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('status', 20)->default('visible'); // visible | hidden
            $table->unsignedInteger('answers_count')->default(0);
            $table->boolean('instructor_answered')->default(false);
            $table->timestamp('last_activity_at');
            $table->timestamps();
            $table->index(['course_id', 'status', 'last_activity_at']);
        });

        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_instructor')->default(false);
            $table->string('status', 20)->default('visible');
            $table->timestamps();
            $table->index(['question_id', 'status']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->longText('body_html'); // sanitised on save
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['course_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_range CHECK (rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        foreach (['announcements', 'answers', 'questions', 'review_reports', 'reviews', 'notification_preferences', 'notifications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
