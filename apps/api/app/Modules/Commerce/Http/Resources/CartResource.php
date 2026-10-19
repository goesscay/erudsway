<?php

namespace App\Modules\Commerce\Http\Resources;

use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Services\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The cart as priced on the server. `token` is returned only for guest carts, for the
 * browser to send back as X-Cart-Token.
 */
class CartResource extends JsonResource
{
    public function __construct(private readonly ?Cart $cart, private readonly Quote $quote)
    {
        parent::__construct($cart);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->cart?->user_id === null ? $this->cart?->guest_token : null,
            'count' => count($this->quote->lines),
            'items' => CartLineResource::collection($this->quote->lines),
            'coupon' => [
                'code' => $this->quote->couponCode,
                'applied' => $this->quote->couponApplied,
                'message' => $this->quote->couponMessage,
            ],
            'totals' => [
                'subtotal' => $this->quote->subtotal(),
                'discount' => $this->quote->discount(),
                'tax' => $this->quote->tax(),
                'total' => $this->quote->total(),
            ],
        ];
    }
}
