<?php

use App\Modules\Engagement\Http\Controllers\AnnouncementController;
use App\Modules\Engagement\Http\Controllers\NotificationController;
use App\Modules\Engagement\Http\Controllers\QaController;
use App\Modules\Engagement\Http\Controllers\ReviewController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::name('engagement.')->group(function () {
    Route::get('courses/{slug}/reviews', [ReviewController::class, 'index'])->name('reviews.index');

    Route::middleware(['auth:sanctum', EnsureAccountActive::class])->group(function () {
        Route::get('courses/{slug}/review', [ReviewController::class, 'mine'])->name('reviews.mine');
        Route::put('courses/{slug}/review', [ReviewController::class, 'save'])->middleware('throttle:20,60')->name('reviews.save');
        Route::delete('courses/{slug}/review', [ReviewController::class, 'destroy'])->name('reviews.destroy');
        Route::post('reviews/{review}/report', [ReviewController::class, 'report'])->middleware('throttle:20,60')->name('reviews.report');

        Route::get('learn/{slug}/questions', [QaController::class, 'index'])->name('qa.index');
        Route::post('learn/{slug}/questions', [QaController::class, 'ask'])->middleware('throttle:20,60')->name('qa.ask');
        Route::get('questions/{question}', [QaController::class, 'show'])->name('qa.show');
        Route::post('questions/{question}/answers', [QaController::class, 'answer'])->middleware('throttle:60,60')->name('qa.answer');
        Route::post('questions/{question}/hide', [QaController::class, 'hideQuestion'])->name('qa.hide-question');
        Route::post('answers/{answer}/hide', [QaController::class, 'hideAnswer'])->name('qa.hide-answer');

        Route::get('learn/{slug}/announcements', [AnnouncementController::class, 'forCourse'])->name('announcements.course');

        Route::get('me/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('me/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('me/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::get('me/notification-preferences', [NotificationController::class, 'preferences'])->name('preferences.show');
        Route::patch('me/notification-preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');

        Route::middleware('can:author-courses')->prefix('instructor')->name('instructor.')->group(function () {
            Route::get('reviews', [ReviewController::class, 'forInstructor'])->name('reviews');
            Route::put('reviews/{review}/reply', [ReviewController::class, 'reply'])->name('reviews.reply');
            Route::get('questions', [QaController::class, 'forInstructor'])->name('questions');
            Route::get('courses/{course:uuid}/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('courses/{course:uuid}/announcements', [AnnouncementController::class, 'store'])->middleware('throttle:10,60')->name('announcements.store');
        });

        Route::middleware('can:'.Permission::ModerateCourses->value)->prefix('admin')->name('admin.')->group(function () {
            Route::get('reviews', [ReviewController::class, 'moderationQueue'])->name('reviews.index');
            Route::post('reviews/{review}/moderate', [ReviewController::class, 'moderate'])->name('reviews.moderate');
        });
    });
});
