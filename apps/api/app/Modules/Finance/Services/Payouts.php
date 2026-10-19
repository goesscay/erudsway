<?php

namespace App\Modules\Finance\Services;

use App\Models\User;
use App\Modules\Finance\Enums\LedgerStatus;
use App\Modules\Finance\Enums\PayoutStatus;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Models\Payout;
use App\Modules\Finance\Models\PayoutAccount;
use App\Modules\Finance\Notifications\PayoutPaid;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Http\DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly payouts (PLAN §7): draft per instructor from available earnings → approved by a
 * second person → paid by bank transfer with its UTR. Ledger entries are linked through
 * payout_items (unique), so an entry can never be paid twice. Refund reversals are
 * included, so a negative balance is netted against later earnings.
 */
class Payouts
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Draft payouts for everyone owed at least the minimum, from earnings available by $periodEnd.
     * $admin is null when the monthly schedule runs it.
     *
     * @return array{created: list<Payout>, skipped: list<array{instructor_id: int, name: string, amount: int, reason: string}>}
     */
    public function generate(?User $admin, Carbon $periodEnd): array
    {
        $owed = LedgerEntry::query()
            ->where('account', 'instructor')
            ->where('status', LedgerStatus::Available->value)
            ->whereNull('payout_id')
            ->where('available_at', '<=', $periodEnd->copy()->endOfDay())
            ->selectRaw('instructor_id, SUM(amount) as total')
            ->groupBy('instructor_id')
            ->get();

        $created = [];
        $skipped = [];
        foreach ($owed as $row) {
            $instructorId = (int) $row->getAttribute('instructor_id');
            $total = (int) $row->getAttribute('total');
            $user = User::query()->find($instructorId);
            $account = PayoutAccount::query()->find($instructorId);

            $reason = match (true) {
                $total <= 0 => 'Balance is not positive (refunds exceed earnings)',
                $total < (int) config('finance.payout_minimum') => 'Below the payout minimum; rolls over',
                $account === null => 'No payout details on file',
                ! $account->isVerified() => 'Payout details not verified yet',
                default => null,
            };
            if ($reason !== null) {
                $skipped[] = ['instructor_id' => $instructorId, 'name' => (string) $user?->name, 'amount' => $total, 'reason' => $reason];

                continue;
            }

            $payout = $this->draft($admin, $instructorId, $account, $periodEnd);
            if ($payout !== null) {
                $created[] = $payout;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function approve(Payout $payout, User $admin): Payout
    {
        $this->expect($payout, PayoutStatus::Draft);
        if (config('finance.maker_checker') && $payout->generated_by === $admin->id) {
            throw new DomainException('SAME_PERSON', 'A payout must be approved by someone other than the person who generated it.', 403);
        }

        $payout->forceFill(['status' => PayoutStatus::Approved, 'approved_by' => $admin->id, 'approved_at' => now()])->save();
        $this->audit->record('payout.approved', $payout, ['status' => 'draft'], ['status' => 'approved'], $admin);

        return $payout;
    }

    /** Record the bank transfer. Linked earnings become paid. */
    public function markPaid(Payout $payout, User $admin, string $reference, Carbon $paidAt): Payout
    {
        DB::transaction(function () use ($payout, $admin, $reference, $paidAt) {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            $this->expect($locked, PayoutStatus::Approved);
            $locked->forceFill(['status' => PayoutStatus::Paid, 'reference' => $reference, 'paid_by' => $admin->id, 'paid_at' => $paidAt])->save();
            LedgerEntry::query()->where('payout_id', $locked->id)->update(['status' => LedgerStatus::Paid->value, 'updated_at' => now()]);
            $this->audit->record('payout.paid', $locked, ['status' => 'approved'], ['status' => 'paid', 'reference' => $reference], $admin);
        });

        $payout->refresh();
        $payout->instructor->notify(new PayoutPaid($payout));

        return $payout;
    }

    /** Drop a draft or approved payout; its earnings go back to the available balance. */
    public function cancel(Payout $payout, User $admin, string $reason): Payout
    {
        DB::transaction(function () use ($payout, $admin, $reason) {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if (! in_array($locked->status, [PayoutStatus::Draft, PayoutStatus::Approved], true)) {
                throw new DomainException('PAYOUT_CLOSED', 'Only draft or approved payouts can be cancelled.', 409);
            }
            LedgerEntry::query()->where('payout_id', $locked->id)->update(['payout_id' => null, 'updated_at' => now()]);
            DB::table('payout_items')->where('payout_id', $locked->id)->delete();
            $locked->forceFill(['status' => PayoutStatus::Cancelled, 'note' => $reason])->save();
            $this->audit->record('payout.cancelled', $locked, null, ['reason' => $reason], $admin);
        });

        return $payout->refresh();
    }

    private function draft(?User $admin, int $instructorId, PayoutAccount $account, Carbon $periodEnd): ?Payout
    {
        return DB::transaction(function () use ($admin, $instructorId, $account, $periodEnd) {
            $entries = LedgerEntry::query()
                ->forInstructor($instructorId)
                ->where('status', LedgerStatus::Available->value)
                ->whereNull('payout_id')
                ->where('available_at', '<=', $periodEnd->copy()->endOfDay())
                ->lockForUpdate()
                ->get();
            $gross = (int) $entries->sum('amount');
            if ($gross < (int) config('finance.payout_minimum')) {
                return null; // changed since we looked
            }

            $rate = (int) config('finance.tds.rate_bps');
            $tds = intdiv($gross * $rate, 10000);
            $payout = Payout::query()->create([
                'instructor_id' => $instructorId,
                'period_end' => $periodEnd->toDateString(),
                'gross' => $gross,
                'tds' => $tds,
                'tds_section' => (string) config('finance.tds.section'),
                'tds_rate_bps' => $rate,
                'net' => $gross - $tds,
                'status' => PayoutStatus::Draft,
                'bank_snapshot' => [
                    'account_holder' => $account->account_holder,
                    'account_last4' => $account->account_last4,
                    'ifsc' => $account->ifsc,
                    'pan_last4' => $account->pan_last4,
                ],
                'generated_by' => $admin?->id,
            ]);

            DB::table('payout_items')->insert($entries->map(fn (LedgerEntry $e) => ['payout_id' => $payout->id, 'ledger_entry_id' => $e->id])->all());
            LedgerEntry::query()->whereIn('id', $entries->pluck('id'))->update(['payout_id' => $payout->id, 'updated_at' => now()]);
            $this->audit->record('payout.generated', $payout, null, ['gross' => $gross, 'tds' => $tds, 'entries' => $entries->count()], $admin);

            return $payout;
        });
    }

    private function expect(Payout $payout, PayoutStatus $status): void
    {
        if ($payout->status !== $status) {
            throw new DomainException('PAYOUT_STATE', "This payout is {$payout->status->value}, not {$status->value}.", 409);
        }
    }
}
