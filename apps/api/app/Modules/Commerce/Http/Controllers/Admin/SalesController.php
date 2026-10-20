<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Enums\RefundStatus;
use App\Modules\Commerce\Http\Resources\OrderResource;
use App\Modules\Commerce\Http\Resources\RefundResource;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\Refund;
use App\Modules\Commerce\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Orders and refund decisions for the finance team (`manage_finance`). */
class SalesController extends Controller
{
    public function orders(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'paid', 'failed', 'cancelled', 'partially_refunded', 'refunded'])],
        ]);

        $orders = Order::query()
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"))))
            ->with(['items.course', 'items.refunds', 'invoice', 'user:id,name,email'])
            ->latest()
            ->paginate(25);

        return OrderResource::collection($orders)->additional([]);
    }

    public function refunds(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(RefundStatus::class)]]);
        $refunds = Refund::query()
            ->where('status', $data['status'] ?? RefundStatus::Requested->value)
            ->with(['orderItem.order.user', 'orderItem.course'])
            ->latest()
            ->get();

        return RefundResource::collection($refunds);
    }

    /** Refund one course of a paid order on the learner's behalf (double charge, no access…). */
    public function refundItem(Request $request, OrderItem $item, RefundService $service): RefundResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']], [
            'reason.min' => 'Say why this order is being refunded (at least 10 characters) — it goes in the audit log.',
        ]);

        return new RefundResource($service->refundByStaff($item, $this->user($request), $data['reason'])->load(['orderItem.order.user', 'orderItem.course']));
    }

    public function approve(Request $request, Refund $refund, RefundService $service): RefundResource
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return new RefundResource($service->approve($refund, $this->user($request), $data['note'] ?? null)->load(['orderItem.order.user', 'orderItem.course']));
    }

    public function reject(Request $request, Refund $refund, RefundService $service): RefundResource
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:1000']]);

        return new RefundResource($service->reject($refund, $this->user($request), $data['note'])->load(['orderItem.order.user', 'orderItem.course']));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
