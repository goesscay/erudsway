<?php

namespace App\Modules\Fulfilment\Console;

use App\Modules\Fulfilment\Simulator\SimulatorAccess;
use Illuminate\Console\Command;

class SweepSimulatorAccess extends Command
{
    protected $signature = 'simulator:sweep';

    protected $description = 'Remove simulator logins whose trial or paid access has ended, and remind learners whose access ends soon';

    public function handle(SimulatorAccess $access): int
    {
        $counts = $access->sweep();
        $this->info("Removed {$counts['removed']}, reminded {$counts['reminded']}, failed {$counts['failed']}.");

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
