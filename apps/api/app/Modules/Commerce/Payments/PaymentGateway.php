<?php

namespace App\Modules\Commerce\Payments;

use App\Modules\Commerce\Models\Order;
use Illuminate\Support\Carbon;

/**
 * What checkout, webhooks, refunds and reconciliation need from a payment provider.
 * Razorpay today (decision D5); another provider can be added without touching orders.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Public key the browser checkout widget needs. */
    public function publicKey(): string;

    /** Create the provider-side order; returns its id. */
    public function createOrder(Order $order): string;

    public function verifyPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool;

    public function fetchPayment(string $paymentId): GatewayPayment;

    public function capture(string $paymentId, int $amount): GatewayPayment;

    /** @param array<string, string> $notes */
    public function refund(string $paymentId, int $amount, array $notes = []): GatewayRefund;

    public function verifyWebhook(string $body, string $signature): bool;

    /** @return list<GatewayPayment> captured payments in the window */
    public function capturedPayments(Carbon $from, Carbon $to): array;
}
