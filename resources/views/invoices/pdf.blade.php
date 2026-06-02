<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ __('Rechnung') }} {{ $invoice->number }}</title>
@php
    $accent = ($template ?? null)?->accent_color ?: '#333';
    if ($accent && ! str_starts_with($accent, '#')) { $accent = '#' . $accent; }
@endphp
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 18px; margin: 0 0 4px; color: {{ $accent }}; }
    .header { display: table; width: 100%; margin-bottom: 16px; }
    .header .left, .header .right { display: table-cell; vertical-align: top; width: 50%; }
    .right { text-align: right; }
    table { border-collapse: collapse; width: 100%; margin-top: 12px; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #ccc; }
    th { background: #f3f3f3; text-align: left; }
    td.num, th.num { text-align: right; }
    tfoot td { border-top: 2px solid {{ $accent }}; font-weight: bold; border-bottom: none; }
    .banner { padding: 8px 12px; margin-bottom: 12px; font-weight: bold; border: 2px solid; }
    .banner-cancelled { color: #b91c1c; border-color: #b91c1c; background: #fee2e2; }
    .banner-credit { color: #b45309; border-color: #b45309; background: #fef3c7; }
    .tpl-header { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #ddd; white-space: pre-line; }
    .tpl-footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 10px; color: #555; white-space: pre-line; }
    .bank-block { margin-top: 18px; padding: 8px 10px; border: 1px solid #ddd; font-size: 10px; }
    .bank-block table { width: 100%; margin: 0; }
    .bank-block table td { border: none; padding: 2px 6px; }
    .bank-block .label { color: #666; width: 25%; }
    @page { margin: 20mm; }
</style>
</head>
<body>

@if (! empty(($template ?? null)?->header_text))
    <div class="tpl-header">{{ $template->header_text }}</div>
@endif

@if ($invoice->isCancelled())
    <div class="banner banner-cancelled">
        {{ __('STORNIERT') }}@if ($invoice->cancelled_at) – {{ $invoice->cancelled_at->format('d.m.Y') }}@endif
        @if ($invoice->cancel_reason)<br><span style="font-weight:normal">{{ $invoice->cancel_reason }}</span>@endif
    </div>
@endif

@if ($invoice->isCreditNote() && $invoice->parent)
    <div class="banner banner-credit">
        {{ __('GUTSCHRIFT / KORREKTURRECHNUNG zu Rechnung :nr vom :date', [
            'nr' => $invoice->parent->number,
            'date' => optional($invoice->parent->issued_on ?? $invoice->parent->created_at)->format('d.m.Y'),
        ]) }}
    </div>
@endif

<div class="header">
    <div class="left">
        <strong>{{ $invoice->customer->name }}</strong><br>
        @if ($invoice->customer->company){{ $invoice->customer->company }}<br>@endif
        {{ $invoice->customer->address_street }}<br>
        {{ $invoice->customer->address_zip }} {{ $invoice->customer->address_city }}<br>
        {{ $invoice->customer->country }}
    </div>
    <div class="right">
        <h1>{{ $invoice->documentLabel() }}</h1>
        <strong>{{ $invoice->number }}</strong><br>
        {{ __('Datum') }}: {{ optional($invoice->issued_on ?? $invoice->created_at)->format('d.m.Y') }}<br>
        @if ($invoice->due_on) {{ __('Fällig') }}: {{ $invoice->due_on->format('d.m.Y') }}<br> @endif
        @if ($invoice->hasServicePeriod())
            {{ $invoice->dateLabelPeriod() }}: {{ $invoice->serviceDateFrom()->format('d.m.Y') }} – {{ $invoice->serviceDateTo()->format('d.m.Y') }}<br>
        @elseif ($invoice->serviceDateSingle())
            {{ $invoice->dateLabelSingle() }}: {{ $invoice->serviceDateSingle()->format('d.m.Y') }}<br>
        @endif
    </div>
</div>

@php $showServiceDates = $invoice->hasServicePeriod(); @endphp

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Beschreibung') }}</th>
            @if ($showServiceDates)<th>{{ $invoice->dateLabelSingle() }}</th>@endif
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
            @if ($showServiceDates)<td>{{ optional($item->service_date)->format('d.m.Y') ?: '—' }}</td>@endif
            <td class="num">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
            <td class="num">{{ number_format((float) $item->unit_price, 2, ',', '.') }} {{ $invoice->currency }}</td>
            <td class="num">{{ number_format((float) $item->amount, 2, ',', '.') }} {{ $invoice->currency }}</td>
        </tr>
    @endforeach
    </tbody>
    @php $footColspan = $showServiceDates ? 5 : 4; @endphp
    <tfoot>
        <tr><td colspan="{{ $footColspan }}" class="num">{{ __('Zwischensumme') }}</td><td class="num">{{ number_format((float) $invoice->subtotal, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        <tr><td colspan="{{ $footColspan }}" class="num">{{ __('USt.') }} {{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%</td><td class="num">{{ number_format((float) $invoice->tax_amount, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
        <tr><td colspan="{{ $footColspan }}" class="num">{{ __('Gesamt') }}</td><td class="num">{{ number_format((float) $invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td></tr>
    </tfoot>
</table>

@if ($invoice->notes)
<p style="margin-top: 16px;">{{ $invoice->notes }}</p>
@endif

@php
    $legal = $orgLegal ?? [];
    $hasOrgBank = ! empty($legal['iban']) || ! empty($legal['bic']) || ! empty($legal['bank_name']);
@endphp
@if ($hasOrgBank && ! $invoice->isCreditNote())
    <div class="bank-block">
        <strong>{{ __('Zahlbar per Überweisung auf folgendes Konto') }}:</strong>
        <table>
            @if (! empty($legal['account_holder']))
                <tr><td class="label">{{ __('Kontoinhaber') }}</td><td>{{ $legal['account_holder'] }}</td></tr>
            @endif
            @if (! empty($legal['bank_name']))
                <tr><td class="label">{{ __('Bank') }}</td><td>{{ $legal['bank_name'] }}</td></tr>
            @endif
            @if (! empty($legal['iban']))
                <tr><td class="label">{{ __('IBAN') }}</td><td>{{ $legal['iban'] }}</td></tr>
            @endif
            @if (! empty($legal['bic']))
                <tr><td class="label">{{ __('BIC') }}</td><td>{{ $legal['bic'] }}</td></tr>
            @endif
            <tr><td class="label">{{ __('Verwendungszweck') }}</td><td>{{ $invoice->number }}</td></tr>
        </table>
    </div>
@endif

@if (! empty(($template ?? null)?->footer_text))
    <div class="tpl-footer">{{ $template->footer_text }}</div>
@endif
</body>
</html>
