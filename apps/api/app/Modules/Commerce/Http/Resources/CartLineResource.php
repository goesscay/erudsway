<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Catalog\Http\Resources\CourseCardResource;
use App\Modules\Commerce\Services\Purchasability;
use App\Modules\Commerce\Services\QuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuoteLine */
class CartLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'course' => new CourseCardResource($this->course),
            'list_price' => $this->listPrice,
            'discount' => $this->discount,
            'amount' => $this->amount,
            /** Why this course can't be bought now (it is then left out of the totals). */
            'problem' => $this->problem,
            'problem_message' => $this->problem ? Purchasability::message($this->problem) : null,
        ];
    }
}
