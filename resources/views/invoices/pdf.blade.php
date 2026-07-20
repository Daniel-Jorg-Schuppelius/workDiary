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
        {{ __('STORNIERT') }}@if ($invoice->cancelled_at) – {{ $invoice->cancelled_at->fdate() }}@endif
        @if ($invoice->cancel_reason)<br><span style="font-weight:normal">{{ $invoice->cancel_reason }}</span>@endif
    </div>
@endif

@if ($invoice->isCreditNote() && $invoice->parent)
    <div class="banner banner-credit">
        {{ __('GUTSCHRIFT / KORREKTURRECHNUNG zu Rechnung :nr vom :date', [
            'nr' => $invoice->parent->number,
            'date' => optional($invoice->parent->issued_on ?? $invoice->parent->created_at)->fdate(),
        ]) }}
    </div>
@endif

@php
    /** @var \App\Services\DocumentDesign\DesignContext $design Feature 076: Blockzustände + Fensterpositionen. */
    $design ??= new \App\Services\DocumentDesign\DesignContext(null);
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
        <h1>{{ $invoice->documentLabel() }}</h1>
        <strong>{{ $invoice->number }}</strong><br>
        {{ __('Datum') }}: {{ optional($invoice->issued_on ?? $invoice->created_at)->fdate() }}<br>
        @if ($invoice->due_on) {{ __('Fällig') }}: {{ $invoice->due_on->fdate() }}<br> @endif
        @if ($invoice->hasServicePeriod())
            {{ $invoice->dateLabelPeriod() }}: {{ $invoice->serviceDateFrom()->fdate() }} – {{ $invoice->serviceDateTo()->fdate() }}<br>
        @elseif ($invoice->serviceDateSingle())
            {{ $invoice->dateLabelSingle() }}: {{ $invoice->serviceDateSingle()->fdate() }}<br>
        @endif
    </div>
</div>

@if ($invoice->isProforma())
    {{-- Unübersehbare Kennzeichnung (MVP-171): kein gesonderter Steuerausweis-Anschein --}}
    <p style="margin: 12px 0; padding: 6px 8px; border: 1px solid #b45309; color: #92400e; font-weight: bold;">
        {{ __('Pro-forma-Rechnung — keine Rechnung im umsatzsteuerlichen Sinn. Kein Vorsteuerabzug möglich.') }}
    </p>
@endif

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
            @if ($showServiceDates)<td>{{ optional($item->service_date)->fdate() ?: '—' }}</td>@endif
            {{-- 3./4. NK nur zeigen, wenn signifikant: die Rechnung muss aus Menge × Preis nachrechenbar sein --}}
            <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->quantity, ((int) round((float) $item->quantity * 1000)) % 10 !== 0 ? 3 : 2, withThousandsSeparator: true) }} {{ $item->unit }}</td>
            <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->unit_price, ((int) round((float) $item->unit_price * 10000)) % 100 !== 0 ? 4 : 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td>
            <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->amount, 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td>
        </tr>
    @endforeach
    </tbody>
    @php
        $footColspan = $showServiceDates ? 5 : 4;
        // Steueraufriss je Satz (§ 14 Abs. 4 UStG): bei gemischten
        // Positions-Steuersätzen MUSS jeder Satz einzeln ausgewiesen werden —
        // ein einzelner Kopfsatz wäre ein falscher Steuerausweis.
        $taxRows = collect($invoice->tax_breakdown ?? []);
        $fmtRate = fn($rate) => rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
    @endphp
    <tfoot>
        @php $docDiscount = $invoice->documentDiscountTotal(); @endphp
        @if ($docDiscount != 0.0)
            {{-- MVP-416: Positionssumme, Belegrabatt, Netto getrennt ausweisen. --}}
            <tr><td colspan="{{ $footColspan }}" class="num">{{ __('Zwischensumme') }}</td><td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($invoice->lineSubtotal(), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
            <tr><td colspan="{{ $footColspan }}" class="num">{{ __('Rabatt') }}@if ($invoice->discount_percent !== null) ({{ $fmtRate($invoice->discount_percent) }}%)@endif</td><td class="num">−{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(abs($docDiscount), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
            <tr><td colspan="{{ $footColspan }}" class="num">{{ __('Netto') }}</td><td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoice->subtotal, 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
        @else
            <tr><td colspan="{{ $footColspan }}" class="num">{{ __('Zwischensumme') }}</td><td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoice->subtotal, 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
        @endif
        @if ($taxRows->count() > 1)
            @foreach ($taxRows as $row)
                <tr><td colspan="{{ $footColspan }}" class="num">{{ __('USt.') }} {{ $fmtRate($row['rate']) }}% ({{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['net'], 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }})</td><td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['tax'], 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
            @endforeach
        @else
            <tr><td colspan="{{ $footColspan }}" class="num">{{ __('USt.') }} {{ $fmtRate($invoice->tax_rate) }}%</td><td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoice->tax_amount, 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
        @endif
        @if ($invoice->is_reverse_charge)
            <tr><td colspan="{{ $footColspan + 1 }}" class="num" style="font-size: 8pt; color: #6b7280;">{{ __('Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge).') }}</td></tr>
        @endif
        @php
            // § 19 UStG (MVP-163, Restpaket): Fußtext auf dem PDF, wenn die
            // Kleinunternehmer-Regelung greift (0 %, kein RC) — auch bei
            // manuell angelegten Belegen ohne TaxResolver-Notiz.
            $smallBusiness = (string) data_get((array) ($invoice->organization?->settings ?? []), 'einvoice.small_business', '0') === '1'
                && ! $invoice->is_reverse_charge
                && (float) $invoice->tax_rate === 0.0
                && ! str_contains((string) $invoice->notes, '§ 19');
        @endphp
        @if ($smallBusiness)
            <tr><td colspan="{{ $footColspan + 1 }}" class="num" style="font-size: 8pt; color: #6b7280;">{{ __('Keine Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).') }}</td></tr>
        @endif
        <tr><td colspan="{{ $footColspan }}" class="num">{{ __('Gesamt') }}</td><td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoice->total, 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}</td></tr>
        @if ($invoice->hasSkonto())
            {{-- MVP-416: Skonto-Kondition mit Frist und Zahlbetrag. --}}
            <tr><td colspan="{{ $footColspan + 1 }}" class="num" style="font-size: 8pt; color: #6b7280;">
                {{ __(':percent % Skonto bei Zahlung innerhalb von :days Tagen', ['percent' => $fmtRate($invoice->skonto_percent), 'days' => (int) $invoice->skonto_days]) }}@if ($invoice->skontoDeadline() !== null) — {{ __('bis :date', ['date' => $invoice->skontoDeadline()->fdate()]) }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $invoice->total - $invoice->skontoAmount(), 2, withThousandsSeparator: true) }} {{ $invoice->currency->value }}@endif
            </td></tr>
        @endif
    </tfoot>
</table>

@if ($invoice->notes)
<p style="margin-top: 16px;">{{ $invoice->notes }}</p>
@endif

@php
    $legal = $orgLegal ?? [];
    $hasOrgBank = ! empty($legal['iban']) || ! empty($legal['bic']) || ! empty($legal['bank_name']);
@endphp
{{-- Feature 076: Bankblock nur, wenn nicht nachweislich auf dem Firmenbogen (MVP-298). --}}
@if ($hasOrgBank && ! $invoice->isCreditNote() && $design->show(\App\Enums\DocumentDesign\InformationBlock::BankDetails))
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
