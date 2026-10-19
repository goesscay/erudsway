<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Commerce\Events\OrderPaid;
use App\Modules\Finance\Services\Ledger;

/** Runs synchronously inside the order's transaction: no paid order without its ledger lines. */
class RecordSale
{
    public function __construct(private readonly Ledger $ledger) {}

    public function handle(OrderPaid $event): void
    {
        $order = $event->order->loadMissing('items.order');
        foreach ($order->items as $item) {
            $this->ledger->recordSale($item, $order->paid_at ?? now());
        }
    }
}
