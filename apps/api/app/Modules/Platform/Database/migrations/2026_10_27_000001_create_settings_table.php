<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin overrides of config values. The key is the config path, e.g. commerce.gst.rate_bps;
        // anything not stored here falls back to config/*.php and .env.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
