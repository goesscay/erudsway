<?php

namespace App\Modules\Commerce\Payments;

/** A provider's view of one payment (amounts in paise). */
final class GatewayPayment
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $id,
        public readonly ?string $orderId,
        public readonly string $status, // created | authorized | captured | refunded | failed
        public readonly int $amount,
        public readonly int $fee = 0,
        public readonly int $tax = 0,
        public readonly ?string $method = null,
        public readonly array $raw = [],
    ) {}

    /** @param array<string, mixed> $entity Razorpay payment entity */
    public static function fromRazorpay(array $entity): self
    {
        return new self(
            id: (string) $entity['id'],
            orderId: isset($entity['order_id']) ? (string) $entity['order_id'] : null,
            status: (string) $entity['status'],
            amount: (int) $entity['amount'],
            fee: (int) ($entity['fee'] ?? 0),
            tax: (int) ($entity['tax'] ?? 0),
            method: isset($entity['method']) ? (string) $entity['method'] : null,
            raw: $entity,
        );
    }

    public function isCaptured(): bool
    {
        return $this->status === 'captured' || $this->status === 'refunded';
    }
}
