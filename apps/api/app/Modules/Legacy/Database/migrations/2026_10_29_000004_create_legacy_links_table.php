<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What each record imported from an old system was created from (e.g. Moodle activity 1622 → two lectures). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_links', function (Blueprint $table) {
            $table->id();
            $table->string('system', 20); // moodle
            $table->string('kind', 20); // course | section | module | user
            $table->string('legacy_id', 64);
            $table->string('role', 20)->default('main'); // e.g. video | notes | quiz | article
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['system', 'kind', 'legacy_id', 'role']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_links');
    }
};
