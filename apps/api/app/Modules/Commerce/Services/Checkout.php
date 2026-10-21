<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\Cart;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Payments\GatewayException;
use App\Modules\Commerce\Payments\PaymentGateway;
use App\Modules\Commerce\Support\Gst;
use App\Modules\Finance\Services\CommissionRules;
use App\Support\Http\DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a cart into a pending order priced entirely on the server, then opens a matching
 * order at the gateway. Nothing here grants access: that happens only when a captured
 * payment is confirmed (OrderCompleter).
 */
class Checkout
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly PaymentGateway $gateway,
        private readonly OrderCompleter $completer,
        private readonly CommissionRules $commission,
    ) {}

    /**
     * @param  array{name: string, state_code: string, gstin?: string|null, business_name?: string|null, address?: string|null}  $billing
     */
    public function start(User $user, Cart $cart, array $billing, Request $request): Order
    {
        $cart->load(['items.course.priceTier', 'items.course.editions.priceTier', 'items.edition.priceTier']);
        $quote = $this->pricing->quote($cart->items, $user, $cart->coupon_code);

        if ($quote->lines === []) {
            throw new DomainException('CART_EMPTY', 'Your cart is empty.', 422);
        }
        if ($quote->hasProblems()) {
            throw new DomainException('CART_CHANGED', 'Some courses in your cart can no longer be bought. Review your cart and try again.', 409);
        }
        if ($cart->coupon_code !== null && ! $quote->couponApplied) {
            throw new DomainException('COUPON_INVALID', $quote->couponMessage ?? 'That code can’t be used.', 409);
        }

        $order = DB::transaction(function () use ($user, $quote, $billing, $request) {
            $order = Order::query()->create([
                'number' => self::newOrderNumber(),
                'user_id' => $user->id,
                'status' => OrderStatus::Pending,
                'currency' => (string) config('commerce.currency'),
                'subtotal' => $quote->subtotal(),
                'discount_total' => $quote->discount(),
                'tax_total' => $quote->tax(),
                'total' => $quote->total(),
                'coupon_code' => $quote->couponApplied ? $quote->couponCode : null,
                'gateway' => $quote->total() > 0 ? $this->gateway->name() : 'none',
                'billing_snapshot' => [
                    'name' => $billing['name'],
                    'email' => $user->email,
                    'state_code' => $billing['state_code'],
                    'state' => Gst::STATES[$billing['state_code']],
                    'gstin' => $billing['gstin'] ?? null,
                    'business_name' => $billing['business_name'] ?? null,
                    'address' => $billing['address'] ?? null,
                ],
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);

            foreach ($quote->payable() as $line) {
                $order->items()->create([
                    'course_id' => $line->course->id,
                    'edition_id' => $line->edition?->id,
                    'upgrade_from_edition_id' => $line->upgradeFrom?->id,
                    'instructor_id' => $line->course->instructor_id,
                    'course_title' => mb_substr($line->title(), 0, 255),
                    'list_price' => $line->listPrice,
                    'discount' => $line->discount,
                    'amount' => $line->amount,
                    'tax' => $line->tax,
                    'net_amount' => $line->netAmount,
                    'coupon_id' => $line->coupon?->id,
                    'channel' => $line->channel,
                    'commission_rate_bps' => $this->commission->rateFor($line->channel, $line->course->instructor_id),
                ]);
            }

            return $order;
        });

        // A 100%-discount order needs no payment (PLAN §7: same pipeline, total 0).
        if ($order->total === 0) {
            $this->completer->completeWithoutPayment($order);

            return $order->refresh();
        }

        try {
            $order->forceFill(['gateway_order_id' => $this->gateway->createOrder($order)])->save();
        } catch (GatewayException $e) {
            $order->forceFill(['status' => OrderStatus::Failed])->save();
            report($e);
            throw new DomainException('GATEWAY_UNAVAILABLE', 'We couldn’t reach our payment partner. Please try again in a moment.', 503);
        }

        return $order;
    }

    /** e.g. ER261019-7K3QZP: date plus a random suffix, short enough for gateway receipts. */
    public static function newOrderNumber(): string
    {
        do {
            $number = 'ER'.now('Asia/Kolkata')->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
