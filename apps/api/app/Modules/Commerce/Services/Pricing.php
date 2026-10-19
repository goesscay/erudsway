<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Commerce\Models\Coupon;
use App\Modules\Commerce\Models\CouponRedemption;
use App\Modules\Commerce\Support\Gst;
use Illuminate\Support\Str;

/**
 * Prices a list of courses for a buyer, with an optional coupon. The browser only ever
 * displays these numbers; checkout calls this again and charges what it says.
 */
class Pricing
{
    public function __construct(private readonly Purchasability $purchasability) {}

    /** @param iterable<Course> $courses */
    public function quote(iterable $courses, ?User $user, ?string $couponCode): Quote
    {
        [$coupon, $couponMessage] = $this->coupon($couponCode, $user);

        $lines = [];
        $couponUsed = false;
        foreach ($courses as $course) {
            $problem = $this->purchasability->problem($course, $user);
            $list = (int) ($course->priceTier->amount ?? 0);
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
        if ($coupon->max_redemptions !== null && $coupon->redemptions()->count() >= $coupon->max_redemptions) {
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
