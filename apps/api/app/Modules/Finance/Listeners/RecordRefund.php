<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Commerce\Events\RefundProcessed;
use App\Modules\Finance\Services\Ledger;

class RecordRefund
{
    public function __construct(private readonly Ledger $ledger) {}

    public function handle(RefundProcessed $event): void
    {
        $this->ledger->recordRefund($event->refund->loadMissing('orderItem.order'));
    }
}
