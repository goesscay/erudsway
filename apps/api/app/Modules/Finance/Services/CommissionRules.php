<?php

namespace App\Modules\Finance\Services;

use App\Models\User;
use App\Modules\Commerce\Enums\SaleChannel;
use App\Modules\Finance\Models\CommissionRule;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The instructor's share of net revenue for a sale (decision D2). An instructor-specific
 * rule beats the platform default for the channel; with no rule, config/commerce.php applies.
 * Checkout snapshots the result on the order item.
 */
class CommissionRules
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function rateFor(SaleChannel $channel, int $instructorId, ?Carbon $at = null): int
    {
        $at ??= now();
        $rule = CommissionRule::query()->activeAt($at)
            ->where('channel', $channel->value)
            ->where(fn ($q) => $q->where('instructor_id', $instructorId)->orWhereNull('instructor_id'))
            ->orderByRaw('instructor_id IS NULL') // instructor-specific first
            ->orderByDesc('effective_from')
            ->first();

        return $rule->instructor_bps ?? (int) config("commerce.commission.{$channel->value}");
    }

    /** Start a new rule, ending the one it replaces for the same channel and scope. */
    public function set(User $admin, SaleChannel $channel, ?int $instructorId, int $bps, ?Carbon $from = null): CommissionRule
    {
        $from ??= now();

        return DB::transaction(function () use ($admin, $channel, $instructorId, $bps, $from) {
            $current = CommissionRule::query()->activeAt($from)
                ->where('channel', $channel->value)
                ->where('instructor_id', $instructorId)
                ->lockForUpdate()
                ->get();
            foreach ($current as $rule) {
                $rule->forceFill(['effective_to' => $from])->save();
            }

            $rule = CommissionRule::query()->create([
                'channel' => $channel,
                'instructor_id' => $instructorId,
                'instructor_bps' => $bps,
                'effective_from' => $from,
                'created_by' => $admin->id,
            ]);
            $this->audit->record('commission.rule_set', $rule, $current->first()?->only(['instructor_bps']), $rule->only(['channel', 'instructor_id', 'instructor_bps']), $admin);

            return $rule;
        });
    }

    public function end(User $admin, CommissionRule $rule): void
    {
        if ($rule->effective_to === null || $rule->effective_to->isFuture()) {
            $rule->forceFill(['effective_to' => now()])->save();
            $this->audit->record('commission.rule_ended', $rule, null, null, $admin);
        }
    }
}
