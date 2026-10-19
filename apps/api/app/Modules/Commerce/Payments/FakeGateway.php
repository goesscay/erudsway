<?php

namespace App\Modules\Commerce\Payments;

use App\Modules\Commerce\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local stand-in for Razorpay so checkout can be exercised without keys. Payments always
 * succeed. Never available in production (see CommerceServiceProvider).
 */
class FakeGateway implements PaymentGateway
{
    public const SECRET = 'fake-gateway-secret';

    public function name(): string
    {
        return 'fake';
    }

    public function publicKey(): string
    {
        return 'fake';
    }

    public function createOrder(Order $order): string
    {
        $id = 'order_fake_'.Str::lower(Str::random(14));
        Cache::put("fake-gateway:{$id}", $order->total, now()->addDay());

        return $id;
    }

    /** The signature a successful fake checkout returns (the web's fake checkout uses this). */
    public static function signatureFor(string $gatewayOrderId, string $paymentId): string
    {
        return hash_hmac('sha256', "{$gatewayOrderId}|{$paymentId}", self::SECRET);
    }

    public function verifyPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool
    {
        return hash_equals(self::signatureFor($gatewayOrderId, $paymentId), $signature);
    }

    /** Fake payment ids embed the order: pay_fake_{gateway order id}. */
    public function fetchPayment(string $paymentId): GatewayPayment
    {
        $orderId = Str::after($paymentId, 'pay_fake_');
        $amount = Cache::get("fake-gateway:{$orderId}");
        if ($amount === null) {
            throw new GatewayException('Unknown fake payment.');
        }

        return new GatewayPayment($paymentId, $orderId, 'captured', (int) $amount, fee: intdiv((int) $amount * 2, 100), tax: intdiv((int) $amount * 36, 10000), method: 'card');
    }

    public function capture(string $paymentId, int $amount): GatewayPayment
    {
        return $this->fetchPayment($paymentId);
    }

    public function refund(string $paymentId, int $amount, array $notes = []): GatewayRefund
    {
        return new GatewayRefund('rfnd_fake_'.Str::lower(Str::random(14)), 'processed');
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        return false;
    }

    public function capturedPayments(Carbon $from, Carbon $to): array
    {
        return [];
    }

    public static function guardAgainstProduction(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The fake payment gateway cannot be used in production. Set the Razorpay keys.');
        }
    }
}
