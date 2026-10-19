<?php

namespace App\Modules\Commerce\Services;

/** A priced cart. Lines with a problem are shown but not charged. */
final class Quote
{
    /** @param list<QuoteLine> $lines */
    public function __construct(
        public readonly array $lines,
        public readonly ?string $couponCode,
        public readonly bool $couponApplied,
        public readonly ?string $couponMessage,
    ) {}

    /** @return list<QuoteLine> */
    public function payable(): array
    {
        return array_values(array_filter($this->lines, fn (QuoteLine $l) => $l->problem === null));
    }

    public function hasProblems(): bool
    {
        return count($this->payable()) !== count($this->lines);
    }

    public function subtotal(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->listPrice, $this->payable()));
    }

    public function discount(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->discount, $this->payable()));
    }

    public function tax(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->tax, $this->payable()));
    }

    public function total(): int
    {
        return array_sum(array_map(fn (QuoteLine $l) => $l->amount, $this->payable()));
    }
}
