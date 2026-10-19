<?php

namespace App\Modules\Commerce\Jobs;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Notifications\OrderReceipt;
use App\Modules\Commerce\Services\InvoiceIssuer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Issue the GST invoice for a paid order, then email the receipt with it attached. */
class IssueInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue('default');
    }

    public function handle(InvoiceIssuer $issuer): void
    {
        $order = Order::query()->with('user')->findOrFail($this->orderId);
        $alreadyIssued = $order->invoice()->whereNotNull('file_path')->exists();
        $invoice = $issuer->issue($order);

        if (! $alreadyIssued) {
            $order->user->notify(new OrderReceipt($order, $invoice));
        }
    }
}
