<?php

namespace App\Modules\Catalog\Models;

use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $amount
 * @property string $currency
 * @property bool $is_active
 */
class PriceTier extends Model
{
    protected $fillable = ['amount', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'is_active' => 'boolean'];
    }

    public function money(): Money
    {
        return new Money($this->amount, $this->currency);
    }
}
