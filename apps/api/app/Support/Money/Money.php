<?php

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Immutable amount in minor units (paise for INR). Never use floats for money.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minor,
        public string $currency = 'INR',
    ) {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid currency code [{$currency}].");
        }
    }

    public static function zero(string $currency = 'INR'): self
    {
        return new self(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * Share expressed in basis points (5000 = 50%), rounded half-up to the nearest paisa.
     */
    public function percentageBps(int $bps): self
    {
        if ($bps < 0 || $bps > 10000) {
            throw new InvalidArgumentException('Basis points must be between 0 and 10000.');
        }

        // Round on the absolute value so refunds (negative amounts) mirror sales exactly.
        $share = intdiv(abs($this->minor) * $bps + 5000, 10000);

        return new self($this->minor < 0 ? -$share : $share, $this->currency);
    }

    /**
     * Split into [share, remainder] so the two parts always add back to the original exactly.
     *
     * @return array{0: self, 1: self}
     */
    public function splitBps(int $bps): array
    {
        $share = $this->percentageBps($bps);

        return [$share, $this->minus($share)];
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    /** @return array{amount: int, currency: string} */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->minor, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}
