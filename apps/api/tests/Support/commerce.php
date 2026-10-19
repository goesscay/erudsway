<?php

use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Payments\PaymentGateway;
use Illuminate\Support\Facades\Http;

/*
| Shared helpers for commerce tests (loaded from tests/Pest.php). Razorpay is exercised
| through its real client against faked HTTP responses.
*/

function useRazorpay(): void
{
    config([
        'commerce.gateway' => 'razorpay',
        'commerce.razorpay.key_id' => 'rzp_test_key',
        'commerce.razorpay.key_secret' => 'test_secret',
        'commerce.razorpay.webhook_secret' => 'webhook_secret',
    ]);
    app()->forgetInstance(PaymentGateway::class);
}

/** A captured Razorpay payment entity for an order. */
function razorpayPayment(Order $order, string $id = 'pay_TEST1', string $status = 'captured', ?int $amount = null): array
{
    return [
        'id' => $id,
        'entity' => 'payment',
        'order_id' => $order->gateway_order_id,
        'status' => $status,
        'amount' => $amount ?? $order->total,
        'currency' => 'INR',
        'method' => 'upi',
        'fee' => 1416,
        'tax' => 216,
    ];
}

/** Fake Razorpay: order creation, payment fetch, capture and refunds. */
function fakeRazorpay(array $payments = [], string $orderId = 'order_TEST1', string $refundStatus = 'pending'): void
{
    // State lives in the container so calling this again updates the same fake.
    app()->instance('fake.razorpay', compact('payments', 'orderId', 'refundStatus'));
    if (app()->bound('fake.razorpay.registered')) {
        return;
    }
    app()->instance('fake.razorpay.registered', true);

    Http::fake(function ($request) {
        ['payments' => $payments, 'orderId' => $orderId, 'refundStatus' => $refundStatus] = app('fake.razorpay');
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($path === '/v1/orders') {
            return Http::response(['id' => $orderId, 'entity' => 'order', 'amount' => $request['amount'], 'status' => 'created']);
        }
        if (preg_match('#^/v1/payments/([^/]+)/refund$#', $path, $m)) {
            return Http::response(['id' => 'rfnd_TEST1', 'entity' => 'refund', 'payment_id' => $m[1], 'amount' => $request['amount'], 'status' => $refundStatus]);
        }
        if (preg_match('#^/v1/payments/([^/]+)(/capture)?$#', $path, $m) && isset($payments[$m[1]])) {
            return Http::response($m[2] ?? false ? [...$payments[$m[1]], 'status' => 'captured'] : $payments[$m[1]]);
        }
        if ($path === '/v1/payments') {
            return Http::response(['entity' => 'collection', 'count' => count($payments), 'items' => array_values($payments)]);
        }

        return Http::response(['error' => ['description' => 'Not found']], 404);
    });
}

function signatureFor(string $orderId, string $paymentId): string
{
    return hash_hmac('sha256', "{$orderId}|{$paymentId}", 'test_secret');
}

/** Billing details accepted by checkout. */
function billing(array $overrides = []): array
{
    return ['name' => 'Priya Raman', 'state_code' => '33', ...$overrides];
}

/** A published paid course (₹599, GST inclusive). */
function paidCourse(): Course
{
    return liveCourse(free: false);
}
