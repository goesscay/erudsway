<?php

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use App\Modules\Learning\Http\Controllers\Admin\EnrollmentController as AdminEnrollments;
use App\Modules\Learning\Http\Controllers\EnrollmentController;
use App\Modules\Learning\Http\Controllers\LearnController;
use App\Modules\Learning\Http\Controllers\NoteController;
use App\Modules\Learning\Http\Controllers\PlaybackController;
use App\Modules\Learning\Http\Controllers\ProgressController;
use Illuminate\Support\Facades\Route;

// Playback. Starting playback works signed out for free previews; the playlist and key
// URLs carry a signed, viewer-bound token instead of a session.
Route::name('learning.')->group(function () {
    Route::post('lectures/{lecture}/playback', [PlaybackController::class, 'issue'])->middleware('throttle:60,1')->name('playback');

    Route::prefix('stream/{asset}')->where(['asset' => '[0-9a-f-]{36}'])->middleware('throttle:600,1')->name('stream.')->group(function () {
        Route::get('master.m3u8', [PlaybackController::class, 'master'])->name('master');
        Route::get('{height}p.m3u8', [PlaybackController::class, 'variant'])->whereNumber('height')->name('variant');
        Route::get('key', [PlaybackController::class, 'key'])->name('key');
    });
});

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->name('learning.')->group(function () {
    Route::get('me/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::get('me/courses/{slug}/enrollment', [EnrollmentController::class, 'status'])->name('enrollments.status');
    Route::post('courses/{slug}/enroll', [EnrollmentController::class, 'store'])->middleware('throttle:20,1')->name('enrollments.store');

    Route::get('learn/{slug}', [LearnController::class, 'show'])->name('course');
    Route::get('learn/lectures/{lecture}', [LearnController::class, 'lecture'])->name('lecture');
    Route::get('learn/resources/{resource}/download', [LearnController::class, 'download'])->name('resources.download');

    Route::put('lectures/{lecture}/progress', [ProgressController::class, 'update'])->middleware('throttle:120,1')->name('progress.update');
    Route::post('lectures/{lecture}/completion', [ProgressController::class, 'complete'])->name('progress.complete');
    Route::delete('lectures/{lecture}/completion', [ProgressController::class, 'uncomplete'])->name('progress.uncomplete');

    Route::get('learn/{slug}/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('lectures/{lecture}/notes', [NoteController::class, 'store'])->middleware('throttle:60,1')->name('notes.store');
    Route::patch('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    Route::middleware('can:'.Permission::ManageUsers->value)->prefix('admin')->name('admin.')->group(function () {
        Route::get('users/{user}/enrollments', [AdminEnrollments::class, 'index'])->name('enrollments.index');
        Route::post('users/{user}/enrollments', [AdminEnrollments::class, 'store'])->name('enrollments.store');
        Route::post('enrollments/{enrollment}/revoke', [AdminEnrollments::class, 'revoke'])->name('enrollments.revoke');
    });
});
