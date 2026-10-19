<?php

namespace App\Modules\Commerce\Console;

use App\Modules\Commerce\Services\Reconciler;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'commerce:reconcile {--days=2 : How many days back to compare}';

    protected $description = 'Compare captured gateway payments with orders; complete missed orders and flag mismatches.';

    public function handle(Reconciler $reconciler): int
    {
        $summary = $reconciler->run(now()->subDays((int) $this->option('days')), now());
        $this->info(sprintf(
            'Checked %d payments: %d orders completed, %d unmatched, %d missing at gateway.',
            $summary['checked'], $summary['completed'], $summary['unmatched'], $summary['missing'],
        ));

        return self::SUCCESS;
    }
}
