{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Angebots-PDF (MVP-650): Muster invoices/pdf — Blockzustände/Fenster über den DesignContext. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ __('Angebot') }} {{ $quote->number }}</title>
@php
    /** @var \App\Services\DocumentDesign\DesignContext $design MVP-651: Texte + Akzentfarbe aus dem Design-Payload. */
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
    $accent = $design->accentColor();
    $fmt = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true);
    $fmtRate = fn ($rate) => rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
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
    .muted { color: #6b7280; }
    .tpl-header { margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #ddd; white-space: pre-line; }
    .tpl-footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #ddd; font-size: 10px; color: #555; white-space: pre-line; }
    .terms { margin-top: 16px; padding: 8px 10px; border: 1px solid #ddd; font-size: 10px; white-space: pre-line; }
    @page { margin: 20mm; }
</style>
</head>
<body>

@if ($design->headerText() !== null)
    <div class="tpl-header">{{ $design->headerText() }}</div>
@endif

@php
    $addressStyle = $design->addressWindowStyle();
    $senderStyle = $design->senderLineStyle();
    $decided = $quote->decided_at !== null;
@endphp
@if ($design->show(\App\Enums\DocumentDesign\InformationBlock::SenderLine) && $senderStyle !== null && $quote->organization !== null)
    <div style="{{ $senderStyle }}">{{ ($orgLegal['account_holder'] ?? null) ?: $quote->organization->name }}</div>
@endif
<div class="header">
    <div class="left"@if ($addressStyle !== null) style="{{ $addressStyle }}"@endif>
        <strong>{{ $quote->customer->name }}</strong><br>
        @if ($quote->customer->company){{ $quote->customer->company }}<br>@endif
        {{ $quote->customer->address_street }}<br>
        {{ $quote->customer->address_zip }} {{ $quote->customer->address_city }}<br>
        {{ $quote->customer->country }}
    </div>
    <div class="right">
        <h1>{{ __('Angebot') }}</h1>
        <strong>{{ $quote->number }}</strong> · V{{ $quote->version }}<br>
        {{ __('Datum') }}: {{ optional($quote->created_at)->fdate() }}<br>
        @if ($quote->valid_until)
            {{ __('Gültig bis') }}: {{ $quote->valid_until->fdate() }}<br>
        @endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Beschreibung') }}</th>
            <th class="num">{{ __('Menge') }}</th>
            <th class="num">{{ __('Einzelpreis') }}</th>
            <th class="num">{{ __('Rabatt') }}</th>
            <th class="num">{{ __('USt %') }}</th>
            <th class="num">{{ __('Betrag') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($quote->items as $item)
        @php
            // Spiegel von Quote::recalculate(): zählt die Position in der Summe?
            $counts = $item->accepted ?? ! $item->optional;
            $marker = null;
            if ($decided && $item->accepted === false) {
                $marker = __('nicht angenommen');
            } elseif (! $decided && $item->optional) {
                $marker = __('Option — nicht in der Gesamtsumme enthalten');
            }
        @endphp
        <tr>
            <td>{{ $item->position }}</td>
            <td>
                {{ $item->description }}
                @if ($marker !== null)<br><span class="muted" style="font-size: 9px;">{{ $marker }}</span>@endif
            </td>
            <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->quantity, ((int) round((float) $item->quantity * 1000)) % 10 !== 0 ? 3 : 2, withThousandsSeparator: true) }} {{ $item->unit }}</td>
            <td class="num">{{ $fmt($item->unit_price?->toFloat() ?? 0.0) }} EUR</td>
            <td class="num">
                @if ($item->discount_percent !== null && (float) $item->discount_percent->getNumericValue() > 0)
                    {{ $fmtRate($item->discount_percent->getNumericValue()) }} %
                @elseif ($item->discount_amount !== null && ! $item->discount_amount->isZero())
                    {{ $fmt($item->discount_amount->toFloat()) }} EUR
                @else
                    —
                @endif
            </td>
            <td class="num">{{ $item->tax_rate !== null ? $fmtRate($item->tax_rate->getNumericValue()) : '—' }}</td>
            <td class="num">{{ $fmt($item->netAmount()->toFloat()) }} EUR</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="6" class="num">{{ __('Zwischensumme (netto)') }}</td><td class="num">{{ $fmt($quote->subtotal?->toFloat() ?? 0.0) }} EUR</td></tr>
        @foreach ($taxRows as $row)
            <tr><td colspan="6" class="num">{{ __('USt.') }} {{ $fmtRate($row['rate']) }}% ({{ $fmt($row['net']) }} EUR)</td><td class="num">{{ $fmt($row['tax']) }} EUR</td></tr>
        @endforeach
        <tr><td colspan="6" class="num">{{ __('Gesamt') }}</td><td class="num">{{ $fmt($quote->total?->toFloat() ?? 0.0) }} EUR</td></tr>
    </tfoot>
</table>

@if ($quote->valid_until)
    <p class="muted" style="margin-top: 12px; font-size: 10px;">
        {{ __('Dieses Angebot ist bis zum :date freibleibend gültig.', ['date' => $quote->valid_until->fdate()]) }}
    </p>
@endif

@if ($quote->terms)
    <div class="terms">
        <strong>{{ __('Bedingungen / Leistungsumfang') }}</strong><br>{{ $quote->terms }}
    </div>
@endif

@if ($design->footerText() !== null)
    <div class="tpl-footer">{{ $design->footerText() }}</div>
@endif
</body>
</html>
