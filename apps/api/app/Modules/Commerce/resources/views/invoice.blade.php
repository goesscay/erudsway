@php
    $rupees = fn (int $paise) => '₹' . number_format($paise / 100, 2);
    $seller = $invoice->seller;
    $buyer = $invoice->buyer;
    $half = $ratePercent / 2;
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tax invoice {{ $invoice->number }}</title>
<style>
    @page { margin: 32px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1b1a17; }
    h1 { font-size: 18px; margin: 0 0 2px; color: #001b40; letter-spacing: .5px; }
    .muted { color: #6b665c; }
    .brand { font-size: 15px; font-weight: bold; color: #001b40; }
    .gold { color: #b48a3c; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: top; }
    .box { border: 1px solid #ddd6c8; padding: 10px 12px; }
    .items th { background: #f3efe6; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; padding: 7px 8px; border-bottom: 1px solid #ddd6c8; }
    .items td { padding: 8px; border-bottom: 1px solid #ece6da; }
    .num { text-align: right; white-space: nowrap; }
    .totals td { padding: 4px 8px; }
    .totals .grand td { font-size: 13px; font-weight: bold; border-top: 1px solid #1b1a17; padding-top: 8px; }
    .foot { margin-top: 28px; font-size: 9.5px; }
</style>
</head>
<body>
<table class="head">
    <tr>
        <td style="width: 60%">
            <div class="brand">ERUDS<span class="gold">WAY</span></div>
            <div><strong>{{ $seller['name'] }}</strong></div>
            @if ($seller['address'])<div class="muted">{{ $seller['address'] }}</div>@endif
            <div class="muted">GSTIN: {{ $seller['gstin'] ?: 'to be added' }} · State: {{ $seller['state'] }} ({{ $seller['state_code'] }})</div>
            <div class="muted">{{ $seller['email'] }}</div>
        </td>
        <td style="width: 40%; text-align: right">
            <h1>TAX INVOICE</h1>
            <div>Invoice no. <strong>{{ $invoice->number }}</strong></div>
            <div>Date: {{ $invoice->issued_at->timezone('Asia/Kolkata')->format('d M Y') }}</div>
            <div class="muted">Order {{ $order->number }}</div>
        </td>
    </tr>
</table>

<table style="margin-top: 18px">
    <tr>
        <td class="box" style="width: 60%">
            <div class="muted" style="font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px">Billed to</div>
            <div><strong>{{ $buyer['name'] }}</strong></div>
            @if ($buyer['name'] !== $buyer['contact'])<div>{{ $buyer['contact'] }}</div>@endif
            @if ($buyer['address'])<div>{{ $buyer['address'] }}</div>@endif
            <div>{{ $buyer['email'] }}</div>
            @if ($buyer['gstin'])<div>GSTIN: {{ $buyer['gstin'] }}</div>@endif
        </td>
        <td class="box" style="width: 40%">
            <div class="muted" style="font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px">Place of supply</div>
            <div>{{ $buyer['state'] }} ({{ $buyer['state_code'] }})</div>
            <div class="muted" style="margin-top: 6px">Supply: online education services · SAC {{ $sac }}</div>
            <div class="muted">Reverse charge: No</div>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 18px">
    <thead>
        <tr><th style="width: 4%">#</th><th style="width: 34%">Course</th><th class="num">List price</th><th class="num">Discount</th><th class="num">Taxable value</th><th class="num">GST {{ $ratePercent }}%</th><th class="num">Amount</th></tr>
    </thead>
    <tbody>
        @foreach ($order->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->course_title }}<div class="muted" style="font-size: 9px">SAC {{ $sac }}</div></td>
                <td class="num">{{ $rupees($item->list_price) }}</td>
                <td class="num">{{ $item->discount ? '−' . $rupees($item->discount) : '—' }}</td>
                <td class="num">{{ $rupees($item->net_amount) }}</td>
                <td class="num">{{ $rupees($item->tax) }}</td>
                <td class="num">{{ $rupees($item->amount) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 10px; width: 45%; margin-left: 55%">
    <tr><td>Taxable value</td><td class="num">{{ $rupees($order->total - $order->tax_total) }}</td></tr>
    @if ($tax['igst'] > 0)
        <tr><td>IGST @ {{ $ratePercent }}%</td><td class="num">{{ $rupees($tax['igst']) }}</td></tr>
    @else
        <tr><td>CGST @ {{ $half }}%</td><td class="num">{{ $rupees($tax['cgst']) }}</td></tr>
        <tr><td>SGST @ {{ $half }}%</td><td class="num">{{ $rupees($tax['sgst']) }}</td></tr>
    @endif
    <tr class="grand"><td>Total paid</td><td class="num">{{ $rupees($order->total) }}</td></tr>
</table>

<div class="foot muted">
    Prices include GST. This is a computer-generated invoice and does not require a signature.
    Paid online{{ $order->paid_at ? ' on ' . $order->paid_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') : '' }}.
</div>
</body>
</html>
