<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ __('Rechnung') }} {{ $invoice->number }}</title>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    .header { display: table; width: 100%; margin-bottom: 16px; }
    .header .left, .header .right { display: table-cell; vertical-align: top; width: 50%; }
    .right { text-align: right; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #ccc; }
    th { background: #f3f3f3; text-align: left; }
    td.num, th.num { text-align: right; }
    tfoot td { border-top: 2px solid #333; font-weight: bold; border-bottom: none; }
    @page { margin: 20mm; }
</style>
</head>
<body>
<div class="header">
    <div class="left">
        <strong>{{ $invoice->customer->name }}</strong><br>
        @if ($invoice->customer->company){{ $invoice->customer->company }}<br>@endif
        {{ $invoice->customer->address_street }}<br>
        {{ $invoice->customer->address_zip }} {{ $invoice->customer->address_city }}<br>
        {{ $invoice->customer->country }}
    </div>
    <div class="right">
        <h1>{{ __('Rechnung') }}</h1>
        <strong>{{ $invoice->number }}</strong><br>
        {{ __('Datum') }}: {{ optional($invoice->issued_on ?? $invoice->created_at)->format('d.m.Y') }}<br>
        @if ($invoice->due_on) {{ __('Fällig') }}: {{ $invoice->due_on->format('d.m.Y') }}<br> @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Beschreibung') }}</th>
            <th class="num">{{ __('Menge') }}</th>
            <th class="num">{{ __('Einzelpreis') }}</th>
            <th class="num">{{ __('Betrag') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($invoice->items as $item)
        <tr>
            <td>{{ $item->position }}</td>
            <td>{{ $item->description }}</td>
            <td class="num">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
            <td class="num">{{ number_format((float) $item->unit_price, 2, ',', '.') }} {{ $invoice->currency }}</td>
            <td class="num">{{ number_format((float) $item->amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="4" class="num">{{ __('Zwischensumme') }}</td><td class="num">{{ number_format((float) $invoice->subtotal, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        <tr><td colspan="4" class="num">{{ __('USt.') }} {{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%</td><td class="num">{{ number_format((float) $invoice->tax_amount, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        <tr><td colspan="4" class="num">{{ __('Gesamt') }}</td><td class="num">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
    </tfoot>
</table>

@if ($invoice->notes)
<p style="margin-top: 16px;">{{ $invoice->notes }}</p>
@endif
</body>
</html>
