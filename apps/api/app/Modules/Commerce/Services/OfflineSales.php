<?php

namespace App\Modules\Commerce\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Catalog\Models\CourseEdition;
use App\Modules\Commerce\Enums\OfflinePaymentMethod;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Commerce\Models\CartItem;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\Gst;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Staff record a course a learner paid for outside the website. It becomes an ordinary paid
 * order (access, GST invoice, receipt, reports), but the platform keeps all of it: offline
 * sales carry no instructor share.
 */
class OfflineSales
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly OrderCompleter $completer,
        private readonly AuditLogger $audit,
    ) {}

    /** What the learner would be charged online for this course or edition (an upgrade if they own a lower one). */
    public function quote(User $buyer, Course $course, ?CourseEdition $edition): QuoteLine
    {
        $course->loadMissing(['priceTier', 'editions.priceTier']);
        $item = new CartItem;
        $item->setRelation('course', $course);
        $item->setRelation('edition', $edition);

        return $this->pricing->quote([$item], $buyer, null)->lines[0];
    }

    /**
     * @param  array{method: OfflinePaymentMethod, reference: string|null, received_on: Carbon, note: string|null}  $payment
     * @param  array{name: string, state_code: string, gstin: string|null, business_name: string|null, address: string|null}  $billing
     */
    public function record(User $staff, User $buyer, Course $course, ?CourseEdition $edition, int $amount, array $payment, array $billing): Order
    {
        $line = $this->quote($buyer, $course, $edition);
        if ($line->problem !== null) {
            throw new DomainException($line->problem, Purchasability::message($line->problem), 422);
        }
        if ($amount < 100 || $amount > $line->listPrice) {
            throw new DomainException('AMOUNT_OUT_OF_RANGE', 'The amount must be between ₹1 and the price, ₹'.number_format($line->listPrice / 100, 2).'.', 422);
        }
        [$net, $tax] = Gst::split($amount);

        $order = DB::transaction(function () use ($buyer, $line, $amount, $net, $tax, $billing) {
            $order = Order::query()->create([
                'number' => Checkout::newOrderNumber(),
                'user_id' => $buyer->id,
                'status' => OrderStatus::Pending,
                'currency' => (string) config('commerce.currency'),
                'subtotal' => $line->listPrice,
                'discount_total' => $line->listPrice - $amount,
                'tax_total' => $tax,
                'total' => $amount,
                'coupon_code' => null,
                'gateway' => Order::GATEWAY_OFFLINE,
                'billing_snapshot' => [
                    'name' => $billing['name'],
                    'email' => $buyer->email,
                    'state_code' => $billing['state_code'],
                    'state' => Gst::STATES[$billing['state_code']],
                    'gstin' => $billing['gstin'],
                    'business_name' => $billing['business_name'],
                    'address' => $billing['address'],
                ],
                'ip' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            ]);
            $order->items()->create([
                'course_id' => $line->course->id,
                'edition_id' => $line->edition?->id,
                'upgrade_from_edition_id' => $line->upgradeFrom?->id,
                'instructor_id' => $line->course->instructor_id,
                'course_title' => mb_substr($line->title(), 0, 255),
                'list_price' => $line->listPrice,
                'discount' => $line->listPrice - $amount,
                'amount' => $amount,
                'tax' => $tax,
                'net_amount' => $net,
                'coupon_id' => null,
                'channel' => SaleChannel::Organic,
                // The platform keeps offline sales in full.
                'commission_rate_bps' => 0,
            ]);

            return $order;
        });

        $details = [
            'method' => $payment['method']->value,
            'reference' => $payment['reference'],
            'received_on' => $payment['received_on']->toDateString(),
            'note' => $payment['note'],
            'recorded_by' => $staff->id,
        ];
        $order = $this->completer->completeOffline($order, $details);
        $this->audit->record('order.offline_payment_recorded', $order, null, [
            'number' => $order->number,
            'buyer' => $buyer->email,
            'course' => $line->title(),
            'amount' => $amount,
            'list_price' => $line->listPrice,
            ...$details,
        ], $staff);

        return $order;
    }
}
