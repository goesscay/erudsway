<?php

namespace App\Modules\Commerce\Payments;

final class GatewayRefund
{
    public function __construct(
        public readonly string $id,
        public readonly string $status, // pending | processed | failed
    ) {}
}
