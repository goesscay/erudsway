<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Commerce\Models\Coupon;

final class QuoteLine
{
    public function __construct(
        public readonly Course $course,
        public readonly int $listPrice,
        public readonly int $discount,
        public readonly int $amount,
        public readonly int $tax,
        public readonly int $netAmount,
        public readonly ?Coupon $coupon,
        public readonly SaleChannel $channel,
        public readonly ?string $problem,
    ) {}
}
