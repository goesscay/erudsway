<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Invoice;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Support\Gst;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * GST tax invoices. Numbers are consecutive within each financial year with no gaps
 * (GST rule 46: unique, at most 16 characters), e.g. ERW/2627/00001.
 */
class InvoiceIssuer
{
    public function issue(Order $order): Invoice
    {
        $invoice = DB::transaction(function () use ($order) {
            $existing = Invoice::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $year = Gst::financialYear($order->paid_at ?? now());
            DB::table('invoice_sequences')->insertOrIgnore(['financial_year' => $year, 'last_number' => 0]);
            $sequence = (int) DB::table('invoice_sequences')->where('financial_year', $year)->lockForUpdate()->value('last_number') + 1;
            DB::table('invoice_sequences')->where('financial_year', $year)->update(['last_number' => $sequence]);

            $billing = $order->billing_snapshot;

            return Invoice::query()->create([
                'order_id' => $order->id,
                'number' => sprintf('%s/%s/%05d', config('commerce.invoice_prefix'), str_replace('-', '', substr($year, 2)), $sequence),
                'financial_year' => $year,
                'seller' => [
                    'name' => (string) config('commerce.seller.name'),
                    'gstin' => config('commerce.seller.gstin'),
                    'state_code' => (string) config('commerce.seller.state_code'),
                    'state' => Gst::STATES[(string) config('commerce.seller.state_code')] ?? null,
                    'address' => config('commerce.seller.address'),
                    'email' => (string) config('commerce.seller.email'),
                ],
                'buyer' => [
                    'name' => $billing['business_name'] ?: $billing['name'],
                    'contact' => $billing['name'],
                    'email' => $billing['email'],
                    'gstin' => $billing['gstin'],
                    'state_code' => $billing['state_code'],
                    'state' => $billing['state'],
                    'address' => $billing['address'],
                ],
                'issued_at' => $order->paid_at ?? now(),
            ]);
        });

        if ($invoice->file_path === null || ! Storage::disk('local')->exists($invoice->file_path)) {
            $path = "invoices/{$invoice->financial_year}/".str_replace('/', '-', $invoice->number).'.pdf';
            Storage::disk('local')->put($path, $this->render($invoice));
            $invoice->forceFill(['file_path' => $path])->save();
        }

        return $invoice;
    }

    public function render(Invoice $invoice): string
    {
        $order = $invoice->order->load('items');
        $tax = Gst::components($order->tax_total, (string) $invoice->buyer['state_code']);

        return Pdf::loadView('commerce::invoice', [
            'invoice' => $invoice,
            'order' => $order,
            'tax' => $tax,
            'ratePercent' => (int) config('commerce.gst.rate_bps') / 100,
            'sac' => (string) config('commerce.gst.sac'),
        ])->setPaper('a4')
            // Embed only the glyphs used: keeps the PDF small enough to email.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }
}
