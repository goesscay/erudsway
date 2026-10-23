<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Services\Editions;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\CouponRedemption;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\Gst;
use App\Modules\Learning\Services\Entitlements;
use Illuminate\Support\Str;

/**
 * Prices a list of courses (or cart lines, which carry an edition) for a buyer, with an
 * optional coupon. The browser only ever displays these numbers; checkout calls this again
 * and charges what it says. An upgrade costs the difference between the two editions.
 */
class Pricing
{
    public function __construct(
        private readonly Purchasability $purchasability,
        private readonly Editions $editions,
        private readonly Entitlements $entitlements,
    ) {}

    /** @param iterable<Course|CartItem> $items */
    public function quote(iterable $items, ?User $user, ?string $couponCode): Quote
    {
        [$coupon, $couponMessage] = $this->coupon($couponCode, $user);

        $lines = [];
        $couponUsed = false;
        foreach ($items as $item) {
            $course = $item instanceof CartItem ? $item->course : $item;
            $edition = $item instanceof CartItem ? $item->edition : null;
            // Lines added before the course had editions are priced at its default edition.
            $edition ??= $course->editions->isNotEmpty() ? $this->editions->default($course) : null;
            $problem = $this->purchasability->problem($course, $user, $edition);
            $upgradeFrom = null;
            $renewal = false;
            if ($edition === null) {
                $list = (int) ($course->priceTier->amount ?? 0);
            } else {
                $current = $user !== null && $problem === null ? $this->entitlements->enrollment($user, $course)?->edition : null;
                // Renewing the same edition costs its full price; a higher edition costs the difference.
                $renewal = $current !== null && $current->id === $edition->id;
                $upgradeFrom = $renewal ? null : $current;
                $list = $current !== null && ! $renewal ? (int) $this->editions->upgradePrice($current, $edition) : $edition->price();
            }
            $applies = $problem === null && $coupon !== null && $this->couponCovers($coupon, $course);
            $discount = $applies ? min($list, $coupon->discountFor($list)) : 0;
            $couponUsed = $couponUsed || $discount > 0;
            [$net, $tax] = Gst::split($list - $discount);

            $lines[] = new QuoteLine(
                course: $course,
                listPrice: $list,
                discount: $discount,
                amount: $list - $discount,
                tax: $tax,
                netAmount: $net,
                coupon: $applies ? $coupon : null,
                channel: $applies ? $coupon->channel : SaleChannel::Organic,
                problem: $problem,
                edition: $edition,
                upgradeFrom: $upgradeFrom,
                renewal: $renewal,
            );
        }

        if ($coupon !== null && ! $couponUsed && $couponMessage === null) {
            $couponMessage = 'This code doesn’t apply to the courses in your cart.';
        }

        return new Quote($lines, $couponCode !== null ? Str::upper($couponCode) : null, $coupon !== null && $couponUsed, $couponMessage);
    }

    /**
     * The active coupon for a code, or why it can't be used.
     *
     * @return array{0: Coupon|null, 1: string|null}
     */
    private function coupon(?string $code, ?User $user): array
    {
        if ($code === null || trim($code) === '') {
            return [null, null];
        }

        $coupon = Coupon::query()->where('code', Str::upper(trim($code)))->first();
        $now = now();
        if ($coupon === null || ! $coupon->is_active) {
            return [null, 'That code isn’t valid.'];
        }
        if (($coupon->starts_at && $coupon->starts_at->isAfter($now)) || ($coupon->ends_at && $coupon->ends_at->isBefore($now))) {
            return [null, 'That code has expired.'];
        }
        // Other people's unpaid checkouts from the last half hour hold a place, so a burst of
        // parallel checkouts can't exceed the limit before any of them is paid.
        $reserved = $coupon->max_redemptions === null ? 0 : Order::query()
            ->where('coupon_code', $coupon->code)->where('status', OrderStatus::Pending->value)
            ->where('created_at', '>=', $now->copy()->subMinutes(30))
            ->when($user, fn ($q) => $q->where('user_id', '!=', $user->id))
            ->count();
        if ($coupon->max_redemptions !== null && $coupon->redemptions()->count() + $reserved >= $coupon->max_redemptions) {
            return [null, 'That code has been fully used.'];
        }
        if ($user !== null && CouponRedemption::query()->where('coupon_id', $coupon->id)->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
            return [null, 'You’ve already used this code.'];
        }

        return [$coupon, null];
    }

    private function couponCovers(Coupon $coupon, Course $course): bool
    {
        if ($coupon->course_id !== null) {
            return $coupon->course_id === $course->id;
        }
        if ($coupon->instructor_id !== null) {
            return $coupon->instructor_id === $course->instructor_id;
        }

        return true;
    }
}
