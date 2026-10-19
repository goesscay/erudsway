<?php

use App\Modules\Certificates\Http\Controllers\CertificateController;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::name('certificates.')->group(function () {
    // Public: verification codes are unguessable and meant to be shared.
    Route::get('certificates/{code}', [CertificateController::class, 'show'])->middleware('throttle:60,1')->name('show');
    Route::get('certificates/{code}/pdf', [CertificateController::class, 'pdf'])->middleware('throttle:20,1')->name('pdf');

    Route::middleware(['auth:sanctum', EnsureAccountActive::class])->group(function () {
        Route::get('me/certificates', [CertificateController::class, 'mine'])->name('mine');
    });
});
