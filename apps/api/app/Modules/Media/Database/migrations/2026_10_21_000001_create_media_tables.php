<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('provider_uid')->nullable()->unique();
            $table->string('status', 20)->default('uploading')->index();
            $table->string('original_name');
            $table->string('source_path')->nullable();
            $table->unsignedBigInteger('source_size_bytes')->default(0);
            $table->string('hls_path')->nullable();
            $table->text('encryption_key')->nullable(); // AES-128 key, encrypted at rest
            $table->json('renditions')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('poster_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecture_id')->constrained()->cascadeOnDelete();
            $table->string('mime', 100);
            $table->unsignedBigInteger('total_bytes');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('total_chunks');
            $table->json('received_chunks');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::table('lectures', function (Blueprint $table) {
            $table->foreignId('video_asset_id')->nullable()->after('kind')->constrained()->nullOnDelete();
            $table->longText('article_html')->nullable()->after('video_asset_id');
        });

        Schema::create('lecture_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecture_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecture_resources');
        Schema::table('lectures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_asset_id');
            $table->dropColumn('article_html');
        });
        Schema::dropIfExists('upload_sessions');
        Schema::dropIfExists('video_assets');
    }
};
