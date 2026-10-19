<?php

use App\Modules\Analytics\Http\Controllers\AnalyticsController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->prefix('admin')->name('analytics.')->group(function () {
    Route::get('queues', [AnalyticsController::class, 'queues'])->name('queues');

    Route::middleware('can:'.Permission::ViewReports->value)->group(function () {
        Route::get('analytics/overview', [AnalyticsController::class, 'overview'])->name('overview');
        Route::get('reports/{name}', [AnalyticsController::class, 'report'])->whereIn('name', ['sales', 'courses', 'instructors', 'refunds'])->name('report');
        Route::get('reports/{name}/csv', [AnalyticsController::class, 'csv'])->whereIn('name', ['sales', 'courses', 'instructors', 'refunds'])->name('csv');
    });
});
