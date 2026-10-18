<?php

namespace App\Support\Http;

use RuntimeException;

/**
 * Throw from services when a business rule fails; rendered as the API error envelope.
 * Example: throw new DomainException('COURSE_NOT_PURCHASED', 'Enrol in this course to watch it.', 403);
 */
class DomainException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
