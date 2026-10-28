<?php

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use App\Modules\Support\Http\Controllers\Admin\SupportQueueController;
use App\Modules\Support\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->name('support.')->group(function () {
    Route::get('me/support/options', [TicketController::class, 'options'])->name('options');
    Route::get('me/support/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('me/support/tickets', [TicketController::class, 'store'])->middleware('throttle:10,60')->name('tickets.store');
    Route::get('me/support/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::post('me/support/tickets/{ticket}/replies', [TicketController::class, 'reply'])->middleware('throttle:30,60')->name('tickets.reply');
    Route::post('me/support/tickets/{ticket}/close', [TicketController::class, 'close'])->name('tickets.close');
    Route::get('support/attachments/{attachment}', [TicketController::class, 'attachment'])->name('attachments.download');

    Route::middleware('can:'.Permission::HandleSupport->value)->prefix('admin/support')->name('admin.')->group(function () {
        Route::get('tickets', [SupportQueueController::class, 'index'])->name('index');
        Route::get('options', [SupportQueueController::class, 'options'])->name('options');
        Route::get('tickets/{ticket}', [SupportQueueController::class, 'show'])->name('show');
        Route::patch('tickets/{ticket}', [SupportQueueController::class, 'update'])->name('update');
        Route::post('tickets/{ticket}/replies', [SupportQueueController::class, 'reply'])->name('reply');
    });
});
