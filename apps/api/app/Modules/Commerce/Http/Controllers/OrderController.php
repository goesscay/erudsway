<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Http\Resources\RefundResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Services\InvoiceIssuer;
use App\Modules\Commerce\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    /** Purchase history: paid and refunded orders, newest first. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->where('user_id', $this->user($request)->id)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::PartiallyRefunded, OrderStatus::Refunded])
            ->with(['items.course', 'items.refunds', 'invoice'])
            ->latest('paid_at')
            ->get();

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $this->user($request)->id, 404);

        return new OrderResource($order->load(['items.course', 'items.refunds', 'invoice']));
    }

    /** The GST invoice PDF (issued on the spot if the background job hasn't run yet). */
    public function invoice(Request $request, Order $order, InvoiceIssuer $issuer): StreamedResponse
    {
        abort_unless($order->user_id === $this->user($request)->id && $order->isPaid() && $order->total > 0, 404);
        $invoice = $issuer->issue($order);

        return Storage::disk('local')->download((string) $invoice->file_path, str_replace('/', '-', $invoice->number).'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /** Ask for a refund of one course (decision D4). */
    public function requestRefund(Request $request, OrderItem $item, RefundService $refunds): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $refund = $refunds->request($this->user($request), $item, $data['reason']);

        return (new RefundResource($refund->load('orderItem.order.user', 'orderItem.course')))->response()->setStatusCode(201);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
