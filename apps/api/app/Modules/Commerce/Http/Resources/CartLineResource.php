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
            /** @var array{id: int, name: string}|null */
            'edition' => $this->edition ? ['id' => $this->edition->id, 'name' => $this->edition->name] : null,
            /** The learner's current edition when this line is an upgrade. */
            /** @var array{id: int, name: string}|null */
            'upgrade_from' => $this->upgradeFrom ? ['id' => $this->upgradeFrom->id, 'name' => $this->upgradeFrom->name] : null,
            'list_price' => $this->listPrice,
            'discount' => $this->discount,
            'amount' => $this->amount,
            /** Why this course can't be bought now (it is then left out of the totals). */
            'problem' => $this->problem,
            'problem_message' => $this->problem ? Purchasability::message($this->problem) : null,
        ];
    }
}
