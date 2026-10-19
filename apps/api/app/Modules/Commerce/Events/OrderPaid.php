<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An order has just been marked paid. Dispatched inside the completing transaction, so
 * synchronous listeners (the ledger) commit or roll back with it.
 */
class OrderPaid
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
