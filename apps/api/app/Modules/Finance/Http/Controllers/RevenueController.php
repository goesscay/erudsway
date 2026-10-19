<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Enums\OrderStatus;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Finance\Enums\LedgerStatus;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Http\Resources\LedgerLineResource;
use App\Modules\Finance\Http\Resources\PayoutAccountResource;
use App\Modules\Finance\Http\Resources\PayoutResource;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Models\Payout;
use App\Modules\Finance\Models\PayoutAccount;
use App\Modules\Finance\Services\Balances;
use App\Modules\Finance\Services\PayoutAccounts;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/** The instructor's own earnings, statement, payouts and payout details. */
class RevenueController extends Controller
{
    public function summary(Request $request, Balances $balances): JsonResponse
    {
        $me = $this->user($request);
        $since = now()->startOfMonth()->subMonths(11);

        $entries = LedgerEntry::query()->forInstructor($me->id)->with('orderItem:id,course_id,course_title')->get(['id', 'amount', 'refund_id', 'order_item_id', 'status', 'available_at', 'created_at']);

        $months = collect(range(0, 11))->map(fn (int $i) => $since->copy()->addMonths($i)->format('Y-m'));
        $byMonth = $entries->filter(fn (LedgerEntry $e) => $e->created_at->gte($since))
            ->groupBy(fn (LedgerEntry $e) => $e->created_at->format('Y-m'))
            ->map(fn ($group) => (int) $group->sum('amount'));

        $sales = OrderItem::query()->where('instructor_id', $me->id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', [OrderStatus::Paid, OrderStatus::PartiallyRefunded, OrderStatus::Refunded]))
            ->with('course:id,slug')
            ->get(['id', 'course_id', 'course_title', 'amount', 'refunded_amount']);
        $earningsByItem = $entries->groupBy('order_item_id')->map(fn ($g) => (int) $g->sum('amount'));

        $nextRelease = $entries->filter(fn (LedgerEntry $e) => $e->status === LedgerStatus::Pending)->sortBy('available_at')->first();

        return ApiResponse::data([
            'balances' => $balances->for($me->id),
            'monthly' => $months->map(fn (string $m) => ['month' => $m, 'earnings' => (int) ($byMonth[$m] ?? 0)])->values()->all(),
            /** @var list<array{slug: string, title: string, sales: int, refunds: int, earnings: int}> */
            'courses' => $sales->groupBy('course_id')->map(fn ($items) => [
                'slug' => (string) $items->first()->course->slug,
                'title' => (string) $items->first()->course_title,
                'sales' => $items->count(),
                'refunds' => $items->filter(fn (OrderItem $i) => $i->refunded_amount > 0)->count(),
                'earnings' => (int) $items->sum(fn (OrderItem $i) => $earningsByItem[$i->id] ?? 0),
            ])->sortByDesc('earnings')->values()->all(),
            'next_release_at' => $nextRelease?->available_at?->toIso8601String(),
            'hold_days' => (int) config('finance.hold_days'),
            'payout_minimum' => (int) config('finance.payout_minimum'),
            'tds' => ['section' => (string) config('finance.tds.section'), 'rate_bps' => (int) config('finance.tds.rate_bps')],
        ]);
    }

    /** Every earning and reversal, newest first. */
    public function statement(Request $request): AnonymousResourceCollection
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return LedgerLineResource::collection(
            LedgerEntry::query()->forInstructor($this->user($request)->id)->latest('id')->paginate(25),
        );
    }

    public function payouts(Request $request): AnonymousResourceCollection
    {
        return PayoutResource::collection(
            Payout::query()->where('instructor_id', $this->user($request)->id)
                ->whereIn('status', [PayoutStatus::Approved->value, PayoutStatus::Paid->value])
                ->with('instructor')->withCount('entries')->latest()->get(),
        );
    }

    public function account(Request $request): JsonResponse
    {
        $account = PayoutAccount::query()->with('user')->find($this->user($request)->id);

        return ApiResponse::data($account ? (new PayoutAccountResource($account))->toArray($request) : null);
    }

    /** Submit or change payout details; any change goes back for verification. */
    public function saveAccount(Request $request, PayoutAccounts $accounts): JsonResponse
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'min:3', 'max:160'],
            'pan' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'gstin' => ['nullable', 'string', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'account_holder' => ['required', 'string', 'min:3', 'max:160'],
            'account_number' => ['required', 'string', 'regex:/^\d{9,18}$/', 'confirmed'],
            'ifsc' => ['required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
        ], [
            'pan.regex' => 'Enter a PAN like ABCDE1234F.',
            'gstin.regex' => 'Enter a valid 15-character GSTIN.',
            'account_number.regex' => 'Enter 9–18 digits.',
            'account_number.confirmed' => 'The account numbers don’t match.',
            'ifsc.regex' => 'Enter an IFSC like HDFC0001234.',
        ]);
        if (! empty($data['gstin']) && substr($data['gstin'], 2, 10) !== $data['pan']) {
            throw ValidationException::withMessages(['gstin' => 'This GSTIN belongs to a different PAN.']);
        }

        $account = $accounts->save($this->user($request), $data);

        return (new PayoutAccountResource($account->load('user')))->response()->setStatusCode(200);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
