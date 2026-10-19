<?php

namespace App\Modules\Commerce\Events;

use App\Modules\Commerce\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;

/** Money has gone back to the learner. Dispatched inside the refund's transaction. */
class RefundProcessed
{
    use Dispatchable;

    public function __construct(public readonly Refund $refund) {}
}
