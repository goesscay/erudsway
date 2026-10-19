<?php

use App\Modules\Assessment\Http\Controllers\AssignmentController;
use App\Modules\Assessment\Http\Controllers\QuizController;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->name('assessment.')->group(function () {
    Route::get('learn/lectures/{lecture}/quiz', [QuizController::class, 'show'])->name('quiz.show');
    Route::post('learn/lectures/{lecture}/quiz/attempts', [QuizController::class, 'submit'])->middleware('throttle:30,1')->name('quiz.submit');

    Route::get('learn/lectures/{lecture}/assignment', [AssignmentController::class, 'show'])->name('assignment.show');
    Route::post('learn/lectures/{lecture}/assignment', [AssignmentController::class, 'submit'])->middleware('throttle:20,60')->name('assignment.submit');
    Route::get('assignment-submissions/{submission}/file', [AssignmentController::class, 'file'])->name('assignment.file');

    Route::middleware('can:author-courses')->prefix('instructor')->name('instructor.')->group(function () {
        Route::get('lectures/{lecture}/quiz', [QuizController::class, 'edit'])->name('quiz.edit');
        Route::put('lectures/{lecture}/quiz', [QuizController::class, 'update'])->name('quiz.update');
        Route::get('lectures/{lecture}/assignment', [AssignmentController::class, 'edit'])->name('assignment.edit');
        Route::put('lectures/{lecture}/assignment', [AssignmentController::class, 'update'])->name('assignment.update');
        Route::get('submissions', [AssignmentController::class, 'inbox'])->name('submissions');
        Route::put('submissions/{submission}/review', [AssignmentController::class, 'review'])->name('submissions.review');
    });
});
