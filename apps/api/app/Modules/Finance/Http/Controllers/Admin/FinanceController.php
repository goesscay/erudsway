<?php

namespace App\Modules\Finance\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Finance\Enums\PayoutAccountStatus;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Http\Resources\CommissionRuleResource;
use App\Modules\Finance\Http\Resources\PayoutAccountResource;
use App\Modules\Finance\Http\Resources\PayoutResource;
use App\Modules\Finance\Models\CommissionRule;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Models\Payout;
use App\Modules\Finance\Models\PayoutAccount;
use App\Modules\Finance\Services\CommissionRules;
use App\Modules\Finance\Services\Ledger;
use App\Modules\Finance\Services\PayoutAccounts;
use App\Modules\Finance\Services\Payouts;
use App\Modules\Finance\Services\Reports;
use App\Support\Http\ApiResponse;
use App\Support\Http\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Finance console (`manage_finance`): payouts, KYC review, commission rules, reports. */
class FinanceController extends Controller
{
    public function payouts(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(PayoutStatus::class)]]);

        return PayoutResource::collection(
            Payout::query()->where('status', $data['status'] ?? PayoutStatus::Draft->value)
                ->with('instructor')->withCount('entries')->latest()->get(),
        );
    }

    /** Draft payouts for everything available up to a date (default: end of last month). */
    public function generate(Request $request, Ledger $ledger, Payouts $payouts): JsonResponse
    {
        $data = $request->validate(['period_end' => ['nullable', 'date', 'before_or_equal:today']]);
        $periodEnd = isset($data['period_end']) ? Carbon::parse($data['period_end']) : now('Asia/Kolkata')->subMonthNoOverflow()->endOfMonth();

        $ledger->release();
        $result = $payouts->generate($this->admin($request), $periodEnd);

        return ApiResponse::data([
            'created' => count($result['created']),
            /** @var list<array{instructor_id: int, name: string, amount: int, reason: string}> */
            'skipped' => $result['skipped'],
        ]);
    }

    public function approve(Request $request, Payout $payout, Payouts $payouts): PayoutResource
    {
        return $this->payout($payouts->approve($payout, $this->admin($request)));
    }

    public function markPaid(Request $request, Payout $payout, Payouts $payouts): PayoutResource
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'min:6', 'max:60'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
        ], ['reference.min' => 'Enter the bank UTR / transfer reference.']);

        return $this->payout($payouts->markPaid($payout, $this->admin($request), $data['reference'], Carbon::parse($data['paid_on'], 'Asia/Kolkata')->setTime(12, 0)));
    }

    public function cancel(Request $request, Payout $payout, Payouts $payouts): PayoutResource
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return $this->payout($payouts->cancel($payout, $this->admin($request), $data['reason']));
    }

    public function accounts(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(PayoutAccountStatus::class)]]);

        return PayoutAccountResource::collection(
            PayoutAccount::query()->where('status', $data['status'] ?? PayoutAccountStatus::Submitted->value)->with('user')->latest('submitted_at')->get(),
        );
    }

    public function verifyAccount(Request $request, User $user, PayoutAccounts $accounts): PayoutAccountResource
    {
        $account = PayoutAccount::query()->findOrFail($user->id);

        return new PayoutAccountResource($accounts->verify($account, $this->admin($request))->load('user'));
    }

    public function rejectAccount(Request $request, User $user, PayoutAccounts $accounts): PayoutAccountResource
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:10', 'max:500']]);
        $account = PayoutAccount::query()->findOrFail($user->id);

        return new PayoutAccountResource($accounts->reject($account, $this->admin($request), $data['note'])->load('user'));
    }

    public function rules(): AnonymousResourceCollection
    {
        return CommissionRuleResource::collection(
            CommissionRule::query()->with('instructor')->orderByRaw('effective_to IS NOT NULL')->latest('effective_from')->get(),
        );
    }

    /** Set the instructor share for a channel, for everyone or one instructor (by email). */
    public function setRule(Request $request, CommissionRules $rules): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::enum(SaleChannel::class)],
            'instructor_email' => ['nullable', 'email'],
            'instructor_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $instructor = null;
        if (! empty($data['instructor_email'])) {
            $instructor = User::query()->where('email', $data['instructor_email'])->first();
            if ($instructor === null || ! $instructor->isInstructor()) {
                throw ValidationException::withMessages(['instructor_email' => 'No instructor with that email.']);
            }
        }

        $rule = $rules->set($this->admin($request), SaleChannel::from($data['channel']), $instructor?->id, (int) round($data['instructor_percent'] * 100));

        return (new CommissionRuleResource($rule->load('instructor')))->response()->setStatusCode(201);
    }

    public function endRule(Request $request, CommissionRule $rule, CommissionRules $rules): CommissionRuleResource
    {
        $rules->end($this->admin($request), $rule);

        return new CommissionRuleResource($rule->refresh()->load('instructor'));
    }

    public function summary(Request $request, Reports $reports): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = $this->period($request);
        $r = $reports->summary($from, $to);

        return ApiResponse::data([
            'from' => $r['from'],
            'to' => $r['to'],
            'sales' => $r['sales'],
            'refunds' => $r['refunds'],
            'split' => $r['split'],
            /** @var list<array{id: string, name: string, sales: int, collected: int, earnings: int, pending: int, available: int}> */
            'instructors' => $r['instructors'],
        ]);
    }

    /** The ledger for a period as CSV, for the accountants. */
    public function ledgerCsv(Request $request): StreamedResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = $this->period($request);

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Transaction', 'Account', 'Instructor', 'Description', 'Amount (INR)', 'Status', 'Order item', 'Refund', 'Payout']);
            LedgerEntry::query()->whereBetween('created_at', [$from, $to])->with('orderItem:id')->orderBy('id')
                ->chunk(500, function ($entries) use ($out) {
                    $names = User::query()->whereIn('id', $entries->pluck('instructor_id')->filter()->unique())->pluck('name', 'id');
                    foreach ($entries as $e) {
                        /** @var LedgerEntry $e */
                        fputcsv($out, Csv::row([
                            $e->created_at->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
                            $e->txn_uuid,
                            $e->account->value,
                            $e->instructor_id ? $names[$e->instructor_id] ?? '' : '',
                            $e->description,
                            number_format($e->amount / 100, 2, '.', ''),
                            $e->status->value,
                            $e->order_item_id,
                            $e->refund_id,
                            $e->payout_id,
                        ]));
                    }
                });
            fclose($out);
        }, sprintf('erudsway-ledger-%s-to-%s.csv', $from->toDateString(), $to->toDateString()), ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $data = $request->only(['from', 'to']);
        $from = isset($data['from']) ? Carbon::parse($data['from'], 'Asia/Kolkata')->startOfDay() : now('Asia/Kolkata')->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to'], 'Asia/Kolkata')->endOfDay() : now('Asia/Kolkata')->endOfDay();

        return [$from, $to];
    }

    private function payout(Payout $payout): PayoutResource
    {
        return new PayoutResource($payout->load('instructor')->loadCount('entries'));
    }

    private function admin(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
