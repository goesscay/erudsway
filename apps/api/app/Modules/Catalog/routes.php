<?php

use App\Modules\Catalog\Http\Controllers\Admin\CategoryManagementController;
use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\CourseController;
use App\Modules\Catalog\Http\Controllers\InstructorController;
use App\Modules\Catalog\Http\Middleware\PublicCache;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

// Public catalogue (no authentication).
Route::middleware(PublicCache::class)->name('catalog.')->group(function () {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('instructors/{slug}', [InstructorController::class, 'show'])->name('instructors.show');
});

// Category management (admin).
Route::middleware(['auth:sanctum', EnsureAccountActive::class, 'can:'.Permission::ManageCategories->value])
    ->prefix('admin/categories')->name('admin.categories.')->group(function () {
        Route::get('/', [CategoryManagementController::class, 'index'])->name('index');
        Route::post('/', [CategoryManagementController::class, 'store'])->name('store');
        Route::put('order', [CategoryManagementController::class, 'reorder'])->name('reorder');
        Route::patch('{category:id}', [CategoryManagementController::class, 'update'])->whereNumber('category')->name('update');
        Route::delete('{category:id}', [CategoryManagementController::class, 'destroy'])->whereNumber('category')->name('destroy');
    });
