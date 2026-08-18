{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : dunning-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mahnschreiben (MVP-650): eigenes PDF je Mahnlauf — Anschreiben, KEIN neuer
     Beleg. Pflichtblöcke der Art „Mahnung": Empfänger, Meta, Identität,
     Summen, Bankverbindung (keine Positionstabelle). --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $level <= 1 ? __('Zahlungserinnerung') : __(':level. Mahnung', ['level' => $level]) }} {{ $invoice->number }}</title>
@php
    /** @var \App\Services\DocumentDesign\DesignContext $design MVP-651: Texte + Akzentfarbe aus dem Design-Payload. */
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
    $accent = $design->accentColor();
    $fmt = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true);
    $openAmount = $invoice->total?->toFloat() ?? 0.0;
    $claimTotal = $openAmount + ($fee ?? 0.0);
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
    .bank-block { margin-top: 18px; padding: 8px 10px; border: 1px solid #ddd; font-size: 10px; }
    .bank-block table { width: 100%; margin: 0; }
    .bank-block table td { border: none; padding: 2px 6px; }
    .bank-block .label { color: #666; width: 25%; }
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
@if ($design->show(\App\Enums\DocumentDesign\InformationBlock::SenderLine) && $senderStyle !== null && $invoice->organization !== null)
    <div style="{{ $senderStyle }}">{{ ($orgLegal['account_holder'] ?? null) ?: $invoice->organization->name }}</div>
@endif
<div class="header">
    <div class="left"@if ($addressStyle !== null) style="{{ $addressStyle }}"@endif>
        <strong>{{ $invoice->customer->name }}</strong><br>
        @if ($invoice->customer->company){{ $invoice->customer->company }}<br>@endif
        {{ $invoice->customer->address_street }}<br>
        {{ $invoice->customer->address_zip }} {{ $invoice->customer->address_city }}<br>
        {{ $invoice->customer->country }}
    </div>
    <div class="right">
        <h1>{{ $level <= 1 ? __('Zahlungserinnerung') : __(':level. Mahnung', ['level' => $level]) }}</h1>
        {{ __('zur Rechnung :nr', ['nr' => $invoice->number]) }}<br>
        {{ __('Datum') }}: {{ now()->fdate() }}<br>
        @if ($payUntil !== null)
            <strong>{{ __('Zahlbar bis') }}: {{ $payUntil->fdate() }}</strong><br>
        @endif
    </div>
</div>

<p>
    @if ($level <= 1)
        {{ __('zur unten aufgeführten Rechnung konnten wir bislang keinen Zahlungseingang feststellen. Sicher handelt es sich um ein Versehen — bitte gleichen Sie den offenen Betrag aus.') }}
    @else
        {{ __('trotz vorheriger Erinnerung ist die unten aufgeführte Rechnung weiterhin offen (Mahnstufe :level). Bitte begleichen Sie die Gesamtforderung umgehend.', ['level' => $level]) }}
    @endif
</p>

<table>
    <thead>
        <tr>
            <th>{{ __('Beleg') }}</th>
            <th>{{ __('Belegdatum') }}</th>
            <th>{{ __('Fällig seit') }}</th>
            <th class="num">{{ __('Offener Betrag') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $invoice->number }}</td>
            <td>{{ optional($invoice->issued_on ?? $invoice->created_at)->fdate() }}</td>
            <td>{{ optional($invoice->due_on)->fdate() ?? '—' }}</td>
            <td class="num">{{ $fmt($openAmount) }} {{ $invoice->currency->value }}</td>
        </tr>
    </tbody>
    @if ($design->show(\App\Enums\DocumentDesign\InformationBlock::Totals))
        <tfoot>
            @if (($fee ?? null) !== null)
                <tr><td colspan="3" class="num">{{ __('Mahngebühr') }}</td><td class="num">{{ $fmt($fee) }} {{ $invoice->currency->value }}</td></tr>
            @endif
            <tr><td colspan="3" class="num">{{ __('Gesamtforderung') }}</td><td class="num">{{ $fmt($claimTotal) }} {{ $invoice->currency->value }}</td></tr>
        </tfoot>
    @endif
</table>

@if ($note !== null)
    <p style="margin-top: 12px;">{{ $note }}</p>
@endif

<p class="muted" style="margin-top: 12px; font-size: 10px;">
    {{ __('Sollte sich dieses Schreiben mit Ihrer Zahlung überschnitten haben, betrachten Sie es bitte als gegenstandslos.') }}
</p>

@php
    $legal = $orgLegal ?? [];
    $hasOrgBank = ! empty($legal['iban']) || ! empty($legal['bic']) || ! empty($legal['bank_name']);
@endphp
@if ($hasOrgBank && $design->show(\App\Enums\DocumentDesign\InformationBlock::BankDetails))
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

@if ($design->footerText() !== null)
    <div class="tpl-footer">{{ $design->footerText() }}</div>
@endif
</body>
</html>
