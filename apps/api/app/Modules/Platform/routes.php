<?php

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use App\Modules\Platform\Http\Controllers\AuditLogController;
use App\Modules\Platform\Http\Controllers\HealthController;
use App\Modules\Platform\Http\Controllers\SettingsController;
use App\Modules\Platform\Http\Controllers\SiteConfigController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('platform.health');
Route::get('site-config', SiteConfigController::class)->name('platform.site-config');

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->prefix('admin')->name('platform.admin.')->group(function () {
    Route::middleware('can:'.Permission::ManageSettings->value)->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::middleware('can:'.Permission::ViewAuditLogs->value)->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('audit-logs/actions', [AuditLogController::class, 'actions'])->name('audit.actions');
        Route::get('audit-logs/csv', [AuditLogController::class, 'csv'])->name('audit.csv');
    });
});
