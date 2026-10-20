<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** One task per promised extra (simulator access, doubt session) per learner and course. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfilment_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 30); // simulator_access | doubt_session
            $table->string('status', 30)->index(); // pending | awaiting_learner | requested | scheduled | done | failed | cancelled
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('edition_id')->nullable()->constrained('course_editions')->nullOnDelete();
            $table->text('data')->nullable(); // encrypted JSON: simulator login, learner's request
            $table->text('staff_notes')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'type']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('manage_fulfilment', 'web');
        Role::findOrCreate('admin', 'web')->givePermissionTo('manage_fulfilment');
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfilment_tasks');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->where('name', 'manage_fulfilment')->delete();
    }
};
