<?php

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Controllers\Admin\InstructorApplicationController as AdminApplications;
use App\Modules\Identity\Http\Controllers\Admin\UserController as AdminUsers;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\EmailVerificationController;
use App\Modules\Identity\Http\Controllers\InstructorApplicationController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

// Guests
Route::prefix('auth')->name('identity.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:password-reset')->name('password.email');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset')->name('password.update');
    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware('throttle:verification')->name('verification.verify');
});

// Signed-in users
Route::middleware(['auth:sanctum', EnsureAccountActive::class])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('identity.logout');
    Route::post('auth/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:verification')->name('identity.verification.send');

    Route::get('me', [MeController::class, 'show'])->name('identity.me');
    Route::patch('me', [MeController::class, 'update'])->name('identity.me.update');
    Route::put('me/password', [MeController::class, 'updatePassword'])->name('identity.me.password');

    Route::post('instructor/applications', [InstructorApplicationController::class, 'store'])->middleware('throttle:10,60')->name('identity.applications.store');
    Route::get('instructor/applications/latest', [InstructorApplicationController::class, 'latest'])->name('identity.applications.latest');

    // Staff
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('can:'.Permission::ReviewInstructorApplications->value)
            ->prefix('instructor-applications')->name('applications.')
            ->group(function () {
                Route::get('/', [AdminApplications::class, 'index'])->name('index');
                Route::get('{application}', [AdminApplications::class, 'show'])->name('show');
                Route::post('{application}/approve', [AdminApplications::class, 'approve'])->name('approve');
                Route::post('{application}/reject', [AdminApplications::class, 'reject'])->name('reject');
            });

        Route::middleware('can:'.Permission::ManageUsers->value)
            ->prefix('users')->name('users.')
            ->group(function () {
                Route::get('/', [AdminUsers::class, 'index'])->name('index');
                Route::post('{user}/suspend', [AdminUsers::class, 'suspend'])->name('suspend');
                Route::post('{user}/reactivate', [AdminUsers::class, 'reactivate'])->name('reactivate');
            });
    });
});
