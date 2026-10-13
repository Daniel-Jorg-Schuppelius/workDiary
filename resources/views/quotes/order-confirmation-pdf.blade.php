{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : order-confirmation-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Auftragsbestätigung als eigener Beleg (MVP-650, Phase 145 MVP-1162): ihre
     Positionen stammen aus den angenommenen Positionen des Angebots. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ __('Auftragsbestätigung') }} {{ $confirmation->number }}</title>
@php
    /** @var \App\Services\DocumentDesign\DesignContext $design MVP-651: Texte + Akzentfarbe aus dem Design-Payload. */
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
    $accent = $design->accentColor();
    $fmt = fn (float $v) => \App\Support\DocumentNumber::decimal($v, 2);
    $fmtRate = fn ($rate) => rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
    $currency = $confirmation->documentCurrency()->value;
    $quote = $confirmation->quote;
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
@endphp
@if ($design->show(\App\Enums\DocumentDesign\InformationBlock::SenderLine) && $senderStyle !== null && $confirmation->organization !== null)
    <div style="{{ $senderStyle }}">{{ ($orgLegal['account_holder'] ?? null) ?: $confirmation->organization->name }}</div>
@endif
<div class="header">
    <div class="left"@if ($addressStyle !== null) style="{{ $addressStyle }}"@endif>
        <strong>{{ $confirmation->customer->name }}</strong><br>
        @if ($confirmation->customer->company){{ $confirmation->customer->company }}<br>@endif
        {{ $confirmation->customer->address_street }}<br>
        {{ $confirmation->customer->address_zip }} {{ $confirmation->customer->address_city }}<br>
        {{ $confirmation->customer->country }}
    </div>
    <div class="right">
        <h1>{{ __('Auftragsbestätigung') }}</h1>
        {{ __('quotes.order_confirmation.pdf.number', ['number' => $confirmation->number]) }}<br>
        @if ($quote !== null)
            {{ __('zu Angebot :nr (V:version)', ['nr' => $quote->number, 'version' => $quote->version]) }}<br>
        @endif
        {{ __('Datum') }}: {{ ($confirmation->confirmed_on ?? $confirmation->created_at)?->fdate() }}<br>
        @if ($quote?->status === \App\Enums\Sales\QuoteStatus::PartiallyAccepted)
            <span class="muted">{{ __('Teilannahme') }}</span><br>
        @endif
    </div>
</div>

<p>{{ __('vielen Dank für Ihren Auftrag. Hiermit bestätigen wir die folgenden angenommenen Positionen:') }}</p>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('Beschreibung') }}</th>
            <th class="num">{{ __('Menge') }}</th>
            <th class="num">{{ __('Einzelpreis') }}</th>
            <th class="num">{{ __('Rabatt') }}</th>
            <th class="num">{{ __('Betrag') }}</th>
        </tr>
    </thead>
    <tbody>
    @foreach (\App\Services\Billing\DocumentOutline::rows($confirmation->items, fn ($line): bool => $line->lineKind()->isPriced()) as $outlineRow)
        @if ($outlineRow['type'] === 'subtotal')
            <tr><td></td><td colspan="4" class="num" style="font-size: 9px;">{{ __('invoicing.line_kind.subtotal', ['number' => $outlineRow['number'], 'title' => $outlineRow['title']->description]) }}</td><td class="num" style="font-weight: bold;">{{ $fmt($outlineRow['amount']->toFloat()) }} {{ $currency }}</td></tr>
            @continue
        @endif
        @php
            $item = $outlineRow['line'];
        @endphp
        @if (! $item->lineKind()->isPriced())
            <tr><td>{{ $outlineRow['number'] }}</td><td colspan="5" style="{{ $item->lineKind() === \App\Enums\Billing\DocumentLineKind::Title ? 'font-weight: bold;' : 'font-style: italic; white-space: pre-line;' }}">{{ $item->description }}</td></tr>
            @continue
        @endif
        <tr>
            <td>{{ $outlineRow['number'] }}</td>
            <td>{{ $item->description }}</td>
            <td class="num">{{ \App\Support\DocumentNumber::decimal((float) $item->quantity, ((int) round((float) $item->quantity * 1000)) % 10 !== 0 ? 3 : 2) }} {{ $item->unit }}</td>
            <td class="num">{{ $fmt($item->unit_price?->toFloat() ?? 0.0) }} {{ $currency }}</td>
            <td class="num">
                @if ($item->discount_percent !== null && (float) $item->discount_percent->getNumericValue() > 0)
                    {{ $fmtRate($item->discount_percent->getNumericValue()) }} %
                @elseif ($item->discount_amount !== null && ! $item->discount_amount->isZero())
                    {{ $fmt($item->discount_amount->toFloat()) }} {{ $currency }}
                @else
                    —
                @endif
            </td>
            <td class="num">{{ $fmt($item->netAmount()->toFloat()) }} {{ $currency }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="5" class="num">{{ __('Zwischensumme (netto)') }}</td><td class="num">{{ $fmt($confirmation->subtotal?->toFloat() ?? 0.0) }} {{ $currency }}</td></tr>
        <tr><td colspan="5" class="num">{{ __('USt.') }}</td><td class="num">{{ $fmt($confirmation->tax_amount?->toFloat() ?? 0.0) }} {{ $currency }}</td></tr>
        <tr><td colspan="5" class="num">{{ __('Gesamt') }}</td><td class="num">{{ $fmt($confirmation->total?->toFloat() ?? 0.0) }} {{ $currency }}</td></tr>
    </tfoot>
</table>

@if ($quote?->terms)
    <p class="muted" style="margin-top: 12px; font-size: 10px;">{{ __('Es gelten die Bedingungen des Angebots :nr.', ['nr' => $quote->number]) }}</p>
@endif

@if ($design->footerText() !== null)
    <div class="tpl-footer">{{ $design->footerText() }}</div>
@endif
</body>
</html>
