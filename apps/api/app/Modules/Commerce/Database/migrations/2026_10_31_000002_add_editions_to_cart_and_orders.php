<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which edition a cart line / order line is for, and — for an upgrade — the edition it upgrades from. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('edition_id')->nullable()->after('course_id')->constrained('course_editions')->nullOnDelete();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('edition_id')->nullable()->after('course_id')->constrained('course_editions')->nullOnDelete();
            $table->foreignId('upgrade_from_edition_id')->nullable()->after('edition_id')->constrained('course_editions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upgrade_from_edition_id');
            $table->dropConstrainedForeignId('edition_id');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edition_id');
        });
    }
};
