<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'position']);
        });

        // Instructors choose from these; amounts in paise.
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('INR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['currency', 'amount']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('price_tier_id')->nullable()->constrained('price_tiers')->restrictOnDelete(); // null = free
            $table->string('slug', 140)->unique();
            $table->string('title', 120);
            $table->string('subtitle', 200)->nullable();
            $table->text('description')->nullable(); // sanitised HTML
            $table->string('language', 30)->default('English');
            $table->string('level', 20);
            $table->string('status', 20)->default('draft');
            $table->string('thumbnail_path')->nullable();
            $table->json('outcomes');
            $table->json('requirements');
            $table->json('audience');
            $table->timestamp('published_at')->nullable();

            // Denormalised for listing/sorting; maintained by enrolment, review and curriculum code.
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('enrollment_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedSmallInteger('lecture_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'category_id']);
            $table->index(['status', 'enrollment_count']);
            $table->index(['status', 'rating_avg']);
            $table->index('instructor_id');
        });

        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        Schema::create('lectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('kind', 20);
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('is_free_preview')->default(false);
            $table->timestamps();

            $table->index(['section_id', 'position']);
            $table->index('course_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

            // Weighted full-text document: title (A), subtitle (B), description without tags (C).
            DB::statement(<<<'SQL'
                ALTER TABLE courses ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (
                    setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                    setweight(to_tsvector('english', coalesce(subtitle, '')), 'B') ||
                    setweight(to_tsvector('english', regexp_replace(coalesce(description, ''), '<[^>]+>', ' ', 'g')), 'C')
                ) STORED
            SQL);
            DB::statement('CREATE INDEX courses_search_vector_idx ON courses USING GIN (search_vector)');
            // Typo-tolerant title matching ("pyhton", "accouting").
            DB::statement('CREATE INDEX courses_title_trgm_idx ON courses USING GIN (lower(title) gin_trgm_ops)');
            DB::statement('ALTER TABLE courses ADD CONSTRAINT courses_rating_avg_range CHECK (rating_avg >= 0 AND rating_avg <= 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lectures');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('price_tiers');
        Schema::dropIfExists('categories');
    }
};
