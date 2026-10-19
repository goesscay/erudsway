<?php

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Services\Ledger;
use Illuminate\Console\Command;

class ReleaseEarnings extends Command
{
    protected $signature = 'finance:release-earnings';

    protected $description = 'Make instructor earnings available once their refund hold has ended.';

    public function handle(Ledger $ledger): int
    {
        $this->info('Released '.$ledger->release().' ledger entries.');

        return self::SUCCESS;
    }
}
