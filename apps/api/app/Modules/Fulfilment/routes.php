<?php

use App\Modules\Fulfilment\Http\Controllers\Admin\FulfilmentController;
use App\Modules\Fulfilment\Http\Controllers\LearnerExtrasController;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->name('fulfilment.')->group(function () {
    Route::get('me/courses/{slug}/extras', [LearnerExtrasController::class, 'show'])->name('extras.show');
    Route::post('me/courses/{slug}/simulator-link', [LearnerExtrasController::class, 'simulatorLink'])->middleware('throttle:20,1')->name('extras.simulator');
    Route::post('me/courses/{slug}/doubt-session', [LearnerExtrasController::class, 'requestDoubtSession'])->middleware('throttle:10,60')->name('extras.doubt');

    Route::middleware('can:manage_fulfilment')->prefix('admin/fulfilment')->name('admin.')->group(function () {
        Route::get('/', [FulfilmentController::class, 'index'])->name('index');
        Route::post('{task}/retry', [FulfilmentController::class, 'retry'])->name('retry');
        Route::patch('{task}', [FulfilmentController::class, 'update'])->name('update');
    });
});
