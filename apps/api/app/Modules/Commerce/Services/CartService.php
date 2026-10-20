<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Catalog\Services\Editions;
use App\Modules\Commerce\Models\Cart;
use App\Support\Http\DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signed-in users have one cart; guests have one keyed by a random token the browser keeps
 * and sends back in the X-Cart-Token header. Signing in merges the guest cart in.
 */
class CartService
{
    public function __construct(
        private readonly Purchasability $purchasability,
        private readonly Editions $editions,
    ) {}

    public function find(?User $user, ?string $guestToken): ?Cart
    {
        if ($user !== null) {
            return Cart::query()->where('user_id', $user->id)->first();
        }
        if ($guestToken !== null && $guestToken !== '') {
            return Cart::query()->whereNull('user_id')->where('guest_token', $guestToken)->first();
        }

        return null;
    }

    public function findOrCreate(?User $user, ?string $guestToken): Cart
    {
        return $this->find($user, $guestToken) ?? ($user !== null
            ? Cart::query()->firstOrCreate(['user_id' => $user->id])
            : Cart::query()->create(['guest_token' => Str::random(48)]));
    }

    /** Add a course, or change which edition of it is in the cart. */
    public function add(Cart $cart, Course $course, ?User $user, ?CourseEdition $edition = null): void
    {
        $edition ??= $course->editions->isNotEmpty() ? $this->editions->default($course) : null;
        $problem = $this->purchasability->problem($course, $user, $edition);
        if ($problem !== null) {
            throw new DomainException($problem, Purchasability::message($problem), 422);
        }
        if ($cart->items()->count() >= (int) config('commerce.cart.max_items')) {
            throw new DomainException('CART_FULL', 'Your cart is full.', 422);
        }

        $cart->items()->updateOrCreate(['course_id' => $course->id], ['edition_id' => $edition?->id]);
        $cart->touch();
    }

    public function remove(Cart $cart, Course $course): void
    {
        $cart->items()->where('course_id', $course->id)->delete();
        $cart->touch();
    }

    /** Move a guest cart's courses (and coupon) into the user's cart, then delete it. */
    public function merge(User $user, string $guestToken): Cart
    {
        return DB::transaction(function () use ($user, $guestToken) {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
            $guest = Cart::query()->whereNull('user_id')->where('guest_token', $guestToken)->lockForUpdate()->first();
            if ($guest === null) {
                return $cart;
            }

            foreach ($guest->items()->with(['course.priceTier', 'edition'])->get() as $item) {
                if ($this->purchasability->problem($item->course, $user, $item->edition) === null) {
                    $cart->items()->updateOrCreate(['course_id' => $item->course_id], ['edition_id' => $item->edition_id]);
                }
            }
            if ($cart->coupon_code === null && $guest->coupon_code !== null) {
                $cart->coupon_code = $guest->coupon_code;
            }
            $cart->touch();
            $cart->save();
            $guest->delete();

            return $cart;
        });
    }

    /**
     * Remove courses the user now owns (after a purchase).
     *
     * @param  iterable<int>  $courseIds
     */
    public function forget(User $user, iterable $courseIds): void
    {
        $cart = Cart::query()->where('user_id', $user->id)->first();
        if ($cart !== null) {
            $cart->items()->whereIn('course_id', collect($courseIds)->all())->delete();
            if (! $cart->items()->exists()) {
                $cart->forceFill(['coupon_code' => null])->save();
            }
        }
    }
}
