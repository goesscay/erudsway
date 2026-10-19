<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Payments\FakeGateway;
use App\Modules\Commerce\Payments\GatewayException;
use App\Modules\Commerce\Payments\PaymentGateway;
use App\Modules\Commerce\Services\CartService;
use App\Modules\Commerce\Services\Checkout;
use App\Modules\Commerce\Services\OrderCompleter;
use App\Modules\Commerce\Support\Gst;
use App\Support\Http\ApiResponse;
use App\Support\Http\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /** States for the billing form (GST place of supply). */
    public function states(): JsonResponse
    {
        return ApiResponse::data(collect(Gst::STATES)->map(fn (string $name, string $code) => ['code' => $code, 'name' => $name])->values()->all());
    }

    /**
     * Create the order from the cart and open payment. The response carries what the
     * Razorpay checkout widget needs; access is granted only after verification.
     */
    public function store(Request $request, CartService $carts, Checkout $checkout): JsonResponse
    {
        $user = $this->user($request);
        if (! $user->hasVerifiedEmail()) {
            throw new DomainException('EMAIL_UNVERIFIED', 'Verify your email address before buying a course.', 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'state_code' => ['required', 'string', Rule::in(array_keys(Gst::STATES))],
            'gstin' => ['nullable', 'string', 'size:15'],
            'business_name' => ['nullable', 'required_with:gstin', 'string', 'max:160'],
            'address' => ['nullable', 'required_with:gstin', 'string', 'max:300'],
        ], ['business_name.required_with' => 'Enter the registered business name for this GSTIN.', 'address.required_with' => 'Enter the business address for the tax invoice.']);

        if (! empty($data['gstin'])) {
            $data['gstin'] = Str::upper($data['gstin']);
            if (! Gst::validGstin($data['gstin'], $data['state_code'])) {
                return ApiResponse::error('VALIDATION_FAILED', 'Check the GSTIN.', 422, ['gstin' => ['This GSTIN isn’t valid for the selected state.']]);
            }
        }

        $cart = $carts->find($user, null);
        if ($cart === null) {
            throw new DomainException('CART_EMPTY', 'Your cart is empty.', 422);
        }

        $order = $checkout->start($user, $cart, $data, $request);
        $order->load('items.course', 'items.refunds', 'invoice');

        return ApiResponse::data([
            'order' => (new OrderResource($order))->toArray($request),
            /** @var array{gateway: string, key: string, gateway_order_id: string, amount: int, currency: string, name: string, description: string, prefill: array{name: string, email: string}, fake_signature: string|null}|null */
            'payment' => $order->status === OrderStatus::Pending ? [
                'gateway' => $this->gateway->name(),
                'key' => $this->gateway->publicKey(),
                'gateway_order_id' => (string) $order->gateway_order_id,
                'amount' => $order->total,
                'currency' => $order->currency,
                'name' => 'Erudsway',
                'description' => $order->items->count() === 1 ? $order->items->first()->course_title : "{$order->items->count()} courses",
                'prefill' => ['name' => $data['name'], 'email' => $user->email],
                // Local development only: lets the fake checkout complete without a gateway.
                'fake_signature' => $this->gateway instanceof FakeGateway
                    ? FakeGateway::signatureFor((string) $order->gateway_order_id, 'pay_fake_'.$order->gateway_order_id)
                    : null,
            ] : null,
        ], status: 201);
    }

    /** Confirm a payment the checkout widget reported as successful. */
    public function verify(Request $request, Order $order, OrderCompleter $completer): OrderResource
    {
        abort_unless($order->user_id === $this->user($request)->id, 404);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:200'],
        ]);

        if (! $order->isPaid()) {
            $valid = $data['razorpay_order_id'] === $order->gateway_order_id
                && $this->gateway->verifyPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature']);
            if (! $valid) {
                throw new DomainException('PAYMENT_UNVERIFIED', 'We couldn’t verify this payment.', 422);
            }

            try {
                $payment = $this->gateway->fetchPayment($data['razorpay_payment_id']);
                if ($payment->status === 'authorized') {
                    $payment = $this->gateway->capture($payment->id, $order->total);
                }
            } catch (GatewayException $e) {
                report($e);
                throw new DomainException('GATEWAY_UNAVAILABLE', 'Your payment went through, but we couldn’t confirm it yet. You’ll get access within a few minutes.', 503);
            }
            $completer->complete($order, $payment);
        }

        return new OrderResource($order->refresh()->load('items.course', 'items.refunds', 'invoice'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
