<?php

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Services\Ledger;
use App\Modules\Finance\Services\Payouts;
use Illuminate\Console\Command;

class GeneratePayouts extends Command
{
    protected $signature = 'finance:generate-payouts';

    protected $description = 'Draft payouts for last month (finance staff approve and pay them).';

    public function handle(Ledger $ledger, Payouts $payouts): int
    {
        $ledger->release();
        $result = $payouts->generate(null, now('Asia/Kolkata')->subMonthNoOverflow()->endOfMonth());
        $this->info(count($result['created']).' payouts drafted, '.count($result['skipped']).' instructors skipped.');

        return self::SUCCESS;
    }
}
