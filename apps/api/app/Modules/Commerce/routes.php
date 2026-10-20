<?php

use App\Modules\Commerce\Http\Controllers\Admin\CouponController;
use App\Modules\Commerce\Http\Controllers\Admin\SalesController;
use App\Modules\Commerce\Http\Controllers\CartController;
use App\Modules\Commerce\Http\Controllers\CheckoutController;
use App\Modules\Commerce\Http\Controllers\OrderController;
use App\Modules\Commerce\Http\Controllers\WebhookController;
use App\Modules\Commerce\Http\Controllers\WishlistController;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use Illuminate\Support\Facades\Route;

Route::name('commerce.')->group(function () {
    // Cart: signed in (session) or guest (X-Cart-Token).
    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('cart/items', [CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
    Route::delete('cart/items/{slug}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:20,1')->name('cart.coupon.apply');
    Route::delete('cart/coupon', [CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

    Route::get('checkout/states', [CheckoutController::class, 'states'])->name('checkout.states');

    // Gateway → us. Authenticated by signature only.
    Route::post('webhooks/razorpay', [WebhookController::class, 'razorpay'])->middleware('throttle:300,1')->name('webhooks.razorpay');

    Route::middleware(['auth:sanctum', EnsureAccountActive::class])->group(function () {
        Route::post('cart/merge', [CartController::class, 'merge'])->name('cart.merge');

        Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
        Route::delete('wishlist/{slug}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

        Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout');
        Route::post('orders/{order}/verify', [CheckoutController::class, 'verify'])->middleware('throttle:checkout')->name('orders.verify');
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
        Route::post('order-items/{item}/refund-request', [OrderController::class, 'requestRefund'])->middleware('throttle:10,60')->name('refunds.request');

        Route::middleware('can:'.Permission::ManageFinance->value)->prefix('admin')->name('admin.')->group(function () {
            Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
            Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');
            Route::patch('coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
            Route::get('orders', [SalesController::class, 'orders'])->name('orders.index');
            Route::get('refunds', [SalesController::class, 'refunds'])->name('refunds.index');
            Route::post('refunds/{refund}/approve', [SalesController::class, 'approve'])->name('refunds.approve');
            Route::post('order-items/{item}/refund', [SalesController::class, 'refundItem'])->name('refunds.issue');
            Route::post('refunds/{refund}/reject', [SalesController::class, 'reject'])->name('refunds.reject');
        });
    });
});
