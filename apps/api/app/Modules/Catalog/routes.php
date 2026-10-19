<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\CourseController;
use App\Modules\Catalog\Http\Controllers\InstructorController;
use App\Modules\Catalog\Http\Middleware\PublicCache;
use Illuminate\Support\Facades\Route;

// Public catalogue (no authentication).
Route::middleware(PublicCache::class)->name('catalog.')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('instructors/{slug}', [InstructorController::class, 'show'])->name('instructors.show');
});
