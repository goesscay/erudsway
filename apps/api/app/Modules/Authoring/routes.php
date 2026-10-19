<?php

use App\Modules\Authoring\Http\Controllers\Admin\CourseManagementController;
use App\Modules\Authoring\Http\Controllers\Admin\CourseReviewController;
use App\Modules\Authoring\Http\Controllers\CourseController;
use App\Modules\Authoring\Http\Controllers\CurriculumController;
use App\Modules\Authoring\Http\Controllers\ResourceController;
use App\Modules\Authoring\Http\Controllers\VideoUploadController;
use App\Modules\Authoring\Http\Middleware\AuditStaffCourseEdits;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->group(function () {
    // Instructors (approved instructor role required)
    Route::middleware(['can:author-courses', AuditStaffCourseEdits::class])->prefix('instructor')->name('authoring.')->group(function () {
        Route::get('price-tiers', [CourseController::class, 'priceTiers'])->name('price-tiers');

        Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
        Route::post('courses', [CourseController::class, 'store'])->middleware('throttle:20,60')->name('courses.store');
        Route::get('courses/{course:uuid}', [CourseController::class, 'show'])->name('courses.show');
        Route::patch('courses/{course:uuid}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('courses/{course:uuid}', [CourseController::class, 'destroy'])->name('courses.destroy');
        Route::post('courses/{course:uuid}/thumbnail', [CourseController::class, 'thumbnail'])->name('courses.thumbnail');
        Route::post('courses/{course:uuid}/submit', [CourseController::class, 'submit'])->name('courses.submit');

        Route::post('courses/{course:uuid}/sections', [CurriculumController::class, 'storeSection'])->name('sections.store');
        Route::put('courses/{course:uuid}/curriculum/order', [CurriculumController::class, 'reorder'])->name('curriculum.reorder');
        Route::patch('sections/{section}', [CurriculumController::class, 'updateSection'])->name('sections.update');
        Route::delete('sections/{section}', [CurriculumController::class, 'destroySection'])->name('sections.destroy');
        Route::post('sections/{section}/lectures', [CurriculumController::class, 'storeLecture'])->name('lectures.store');
        Route::patch('lectures/{lecture}', [CurriculumController::class, 'updateLecture'])->name('lectures.update');
        Route::delete('lectures/{lecture}', [CurriculumController::class, 'destroyLecture'])->name('lectures.destroy');

        Route::post('lectures/{lecture}/resources', [ResourceController::class, 'store'])->name('resources.store');
        Route::delete('resources/{resource}', [ResourceController::class, 'destroy'])->name('resources.destroy');
        Route::get('resources/{resource}/download', [ResourceController::class, 'download'])->name('resources.download');

        Route::post('lectures/{lecture}/video-uploads', [VideoUploadController::class, 'start'])->middleware('throttle:30,60')->name('uploads.start');
        Route::get('uploads/{session}', [VideoUploadController::class, 'show'])->name('uploads.show');
        Route::put('uploads/{session}/chunks/{index}', [VideoUploadController::class, 'chunk'])
            ->whereNumber('index')->withoutMiddleware('throttle:api')->middleware('throttle:upload-chunks')->name('uploads.chunk');
        Route::post('uploads/{session}/complete', [VideoUploadController::class, 'complete'])->name('uploads.complete');
        Route::get('videos/{uuid}', [VideoUploadController::class, 'asset'])->name('videos.show');
    });

    // Moderation
    Route::middleware('can:'.Permission::ModerateCourses->value)->prefix('admin')->name('admin.courses.')->group(function () {
        Route::get('course-submissions', [CourseReviewController::class, 'index'])->name('submissions.index');
        Route::post('course-submissions/{submission}/approve', [CourseReviewController::class, 'approve'])->name('submissions.approve');
        Route::post('course-submissions/{submission}/reject', [CourseReviewController::class, 'reject'])->name('submissions.reject');
        Route::get('courses/{course:uuid}', [CourseReviewController::class, 'show'])->name('show');
        Route::post('courses/{course:uuid}/unpublish', [CourseReviewController::class, 'unpublish'])->name('unpublish');
    });

    // Catalogue management (any course, any state)
    Route::middleware('can:'.Permission::ManageCourses->value)->prefix('admin')->name('admin.courses.')->group(function () {
        Route::get('courses', [CourseManagementController::class, 'index'])->name('index');
        Route::post('courses/{course:uuid}/publish', [CourseManagementController::class, 'publish'])->name('publish');
        Route::post('courses/{course:uuid}/archive', [CourseManagementController::class, 'archive'])->name('archive');
        Route::post('courses/{course:uuid}/restore', [CourseManagementController::class, 'restore'])->name('restore');
        Route::put('courses/{course:uuid}/instructor', [CourseManagementController::class, 'changeInstructor'])->name('instructor');
        Route::put('courses/{course:uuid}/featured', [CourseManagementController::class, 'feature'])->name('featured');
    });
});
