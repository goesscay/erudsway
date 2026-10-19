<?php

namespace App\Modules\Commerce\Payments;

use App\Modules\Commerce\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay over its REST API (https://razorpay.com/docs/api/). Signatures are plain
 * HMAC-SHA256, checked in constant time.
 */
class RazorpayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function publicKey(): string
    {
        return $this->config('key_id');
    }

    public function createOrder(Order $order): string
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/orders', [
            'amount' => $order->total,
            'currency' => $order->currency,
            'receipt' => $order->number,
            'notes' => ['order_uuid' => $order->uuid],
        ]));

        return (string) $response->json('id');
    }

    public function verifyPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', "{$gatewayOrderId}|{$paymentId}", $this->config('key_secret')), $signature);
    }

    public function fetchPayment(string $paymentId): GatewayPayment
    {
        return GatewayPayment::fromRazorpay($this->send(fn (PendingRequest $http) => $http->get('/payments/'.rawurlencode($paymentId)))->json());
    }

    public function capture(string $paymentId, int $amount): GatewayPayment
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/payments/'.rawurlencode($paymentId).'/capture', [
            'amount' => $amount,
            'currency' => 'INR',
        ]));

        return GatewayPayment::fromRazorpay($response->json());
    }

    public function refund(string $paymentId, int $amount, array $notes = []): GatewayRefund
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post('/payments/'.rawurlencode($paymentId).'/refund', [
            'amount' => $amount,
            'speed' => 'normal',
            'notes' => $notes ?: (object) [],
        ]));

        return new GatewayRefund((string) $response->json('id'), (string) $response->json('status'));
    }

    public function verifyWebhook(string $body, string $signature): bool
    {
        $secret = (string) config('commerce.razorpay.webhook_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    public function capturedPayments(Carbon $from, Carbon $to): array
    {
        $payments = [];
        $skip = 0;
        do {
            $page = $this->send(fn (PendingRequest $http) => $http->get('/payments', [
                'from' => $from->getTimestamp(),
                'to' => $to->getTimestamp(),
                'count' => 100,
                'skip' => $skip,
            ]))->json('items', []);
            foreach ($page as $entity) {
                $payment = GatewayPayment::fromRazorpay($entity);
                if ($payment->isCaptured()) {
                    $payments[] = $payment;
                }
            }
            $skip += 100;
        } while (count($page) === 100);

        return $payments;
    }

    /** @param callable(PendingRequest): Response $call */
    private function send(callable $call): Response
    {
        $http = Http::baseUrl((string) config('commerce.razorpay.base_url'))
            ->withBasicAuth($this->config('key_id'), $this->config('key_secret'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('commerce.razorpay.timeout'));

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            throw new GatewayException('Could not reach Razorpay.', 0, $e);
        }

        if ($response->failed()) {
            $message = (string) ($response->json('error.description') ?? 'Razorpay request failed.');
            throw new GatewayException($message, $response->status());
        }

        return $response;
    }

    private function config(string $key): string
    {
        $value = (string) config("commerce.razorpay.{$key}");
        if ($value === '') {
            throw new RuntimeException("Razorpay is not configured: commerce.razorpay.{$key} is empty.");
        }

        return $value;
    }
}
