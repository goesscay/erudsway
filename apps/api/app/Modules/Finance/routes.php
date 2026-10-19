<?php

use App\Modules\Finance\Http\Controllers\Admin\FinanceController;
use App\Modules\Finance\Http\Controllers\RevenueController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', EnsureAccountActive::class])->name('finance.')->group(function () {
    Route::middleware('can:author-courses')->prefix('instructor')->group(function () {
        Route::get('revenue', [RevenueController::class, 'summary'])->name('revenue');
        Route::get('statement', [RevenueController::class, 'statement'])->name('statement');
        Route::get('payouts', [RevenueController::class, 'payouts'])->name('payouts');
        Route::get('payout-account', [RevenueController::class, 'account'])->name('payout-account.show');
        Route::put('payout-account', [RevenueController::class, 'saveAccount'])->middleware('throttle:10,60')->name('payout-account.save');
    });

    Route::middleware('can:'.Permission::ManageFinance->value)->prefix('admin')->name('admin.')->group(function () {
        Route::get('payouts', [FinanceController::class, 'payouts'])->name('payouts.index');
        Route::post('payouts/generate', [FinanceController::class, 'generate'])->name('payouts.generate');
        Route::post('payouts/{payout}/approve', [FinanceController::class, 'approve'])->name('payouts.approve');
        Route::post('payouts/{payout}/mark-paid', [FinanceController::class, 'markPaid'])->name('payouts.paid');
        Route::post('payouts/{payout}/cancel', [FinanceController::class, 'cancel'])->name('payouts.cancel');

        Route::get('payout-accounts', [FinanceController::class, 'accounts'])->name('accounts.index');
        Route::post('payout-accounts/{user}/verify', [FinanceController::class, 'verifyAccount'])->name('accounts.verify');
        Route::post('payout-accounts/{user}/reject', [FinanceController::class, 'rejectAccount'])->name('accounts.reject');

        Route::get('commission-rules', [FinanceController::class, 'rules'])->name('rules.index');
        Route::post('commission-rules', [FinanceController::class, 'setRule'])->name('rules.store');
        Route::post('commission-rules/{rule}/end', [FinanceController::class, 'endRule'])->name('rules.end');

        Route::get('finance/summary', [FinanceController::class, 'summary'])->name('summary');
        Route::get('finance/ledger.csv', [FinanceController::class, 'ledgerCsv'])->name('ledger.csv');
    });
});
