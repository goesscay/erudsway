<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A signed-in user's cart, or a guest's (identified by an unguessable token the browser keeps).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_token
 * @property string|null $coupon_code
 * @property Carbon $updated_at
 * @property-read Collection<int, CartItem> $items
 */
class Cart extends Model
{
    protected $fillable = ['user_id', 'guest_token', 'coupon_code'];

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
