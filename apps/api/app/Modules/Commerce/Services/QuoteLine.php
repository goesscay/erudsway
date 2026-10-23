<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
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
        public readonly ?CourseEdition $edition = null,
        public readonly ?CourseEdition $upgradeFrom = null,
        public readonly bool $renewal = false,
    ) {}

    /** What the invoice line says: the course, plus the edition (and upgrade) where there is one. */
    public function title(): string
    {
        if ($this->edition === null) {
            return $this->course->title;
        }

        $note = match (true) {
            $this->upgradeFrom !== null => " (upgrade from {$this->upgradeFrom->name})",
            $this->renewal => ' (renewal)',
            default => '',
        };

        return $this->course->title.' — '.$this->edition->name.$note;
    }
}
