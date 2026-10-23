<?php

namespace App\Support;

use Closure;

/** Indian mobile numbers, however they're typed, stored one way: "+91 98765 43210". */
final class Phone
{
    public const MESSAGE = 'Enter a 10-digit Indian mobile number, e.g. 98765 43210.';

    public static function normalise(?string $input): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', (string) $input) ?? '';
        $digits = match (true) {
            str_starts_with($digits, '+91') => substr($digits, 3),
            strlen($digits) === 12 && str_starts_with($digits, '91') => substr($digits, 2),
            strlen($digits) === 11 && str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 ? '+91 '.substr($digits, 0, 5).' '.substr($digits, 5) : null;
    }

    /** Validation rule: the value must be a valid Indian mobile number. */
    public static function rule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (self::normalise(is_string($value) ? $value : null) === null) {
                $fail(self::MESSAGE);
            }
        };
    }
}
