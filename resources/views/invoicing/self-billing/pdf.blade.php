{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gutschrift im Gutschriftsverfahren (MVP-1280). Variablen: $credit, $issuer, $totals.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('invoicing.self_billing.document_title') }} {{ $credit->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 20px; margin: 18px 0 4px; }
        .parties { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .parties td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        .label { color: #666; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; }
        table.meta, table.lines, table.totals { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.meta td { padding: 2px 6px 2px 0; }
        table.lines th, table.lines td { padding: 4px 6px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        table.lines th { background: #f5f5f5; }
        .num { text-align: right !important; white-space: nowrap; }
        table.totals { width: 50%; margin-left: 50%; }
        table.totals td { padding: 3px 6px; }
        table.totals tr.sum td { font-weight: 700; border-top: 2px solid #111; }
        .banner { margin-top: 10px; padding: 6px 10px; border: 2px solid #b91c1c; color: #b91c1c; font-weight: 700; }
        .note { margin-top: 18px; font-size: 10px; color: #444; }
    </style>
</head>
<body>
    <table class="parties">
        <tr>
            <td>
                <div class="label">{{ __('invoicing.self_billing.pdf.issuer') }}</div>
                <strong>{{ $issuer['name'] }}</strong><br>
                {{ $issuer['street'] }}<br>{{ $issuer['zip'] }} {{ $issuer['city'] }}
                @if ($issuer['vat_id'] !== '')<br>{{ __('USt-IdNr.') }}: {{ $issuer['vat_id'] }}@endif
            </td>
            <td>
                <div class="label">{{ __('invoicing.self_billing.pdf.supplier') }}</div>
                <strong>{{ $credit->supplier->company ?: $credit->supplier->name }}</strong><br>
                {{ $credit->supplier->address_street }}<br>{{ $credit->supplier->address_zip }} {{ $credit->supplier->address_city }}
                @if (trim((string) $credit->supplier->vat_id) !== '')
                    <br>{{ __('USt-IdNr.') }}: {{ $credit->supplier->vat_id }}
                @elseif (trim((string) $credit->supplier->tax_number) !== '')
                    <br>{{ __('Steuernummer') }}: {{ $credit->supplier->tax_number }}
                @endif
            </td>
        </tr>
    </table>

    {{-- Pflichtangabe nach § 14 Abs. 4 Satz 1 Nr. 10 UStG. --}}
    <h1>{{ __('invoicing.self_billing.document_title') }}</h1>
    @if ($credit->status === \App\Enums\Invoicing\SelfBillingStatus::Cancelled)
        <div class="banner">{{ __('invoicing.self_billing.pdf.cancelled', ['date' => $credit->cancelled_at?->orgTz()->format('d.m.Y')]) }}</div>
    @elseif ($credit->isDraft())
        <div class="banner">{{ __('invoicing.self_billing.pdf.draft') }}</div>
    @endif

    <table class="meta">
        <tr><td>{{ __('invoicing.self_billing.field.number') }}</td><td><strong>{{ $credit->number ?? '—' }}</strong></td></tr>
        <tr><td>{{ __('invoicing.self_billing.field.issued_on') }}</td><td>{{ $credit->issued_on?->format('d.m.Y') ?? '—' }}</td></tr>
        <tr><td>{{ __('invoicing.self_billing.field.service_period') }}</td><td>{{ $credit->service_from?->format('d.m.Y') ?? '—' }} – {{ $credit->service_until?->format('d.m.Y') ?? '—' }}</td></tr>
    </table>

    <table class="lines">
        <tr>
            <th>#</th>
            <th>{{ __('invoicing.self_billing.field.description') }}</th>
            <th class="num">{{ __('invoicing.self_billing.field.quantity') }}</th>
            <th class="num">{{ __('invoicing.self_billing.field.unit_price') }}</th>
            <th class="num">{{ __('invoicing.self_billing.field.tax_rate') }}</th>
            <th class="num">{{ __('invoicing.self_billing.field.net') }}</th>
        </tr>
        @foreach ($credit->lines as $line)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $line->description }}</td>
                <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $line->lineQuantity(), 2) }} {{ $line->unit }}</td>
                <td class="num">{{ $line->unitPrice()?->format() ?? '—' }}</td>
                <td class="num">{{ $line->taxRate()?->format() ?? '0 %' }}</td>
                <td class="num">{{ $line->netAmount()->format() }}</td>
            </tr>
        @endforeach
    </table>

    <table class="totals">
        <tr><td>{{ __('invoicing.self_billing.field.net') }}</td><td class="num">{{ $totals['subtotal']->format() }}</td></tr>
        @foreach ($totals['by_rate'] as $group)
            <tr><td>{{ __('invoicing.self_billing.pdf.tax_at', ['rate' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $group['rate'], 0)]) }}</td><td class="num">{{ $group['tax']->format() }}</td></tr>
        @endforeach
        <tr class="sum"><td>{{ __('invoicing.self_billing.field.gross') }}</td><td class="num">{{ $totals['total']->format() }}</td></tr>
    </table>

    <p class="note">
        {{ __('invoicing.self_billing.pdf.agreement', ['date' => $credit->supplier->self_billing_agreed_on?->format('d.m.Y') ?? '—']) }}
        @if (trim((string) $credit->supplier->bank_iban) !== '')
            <br>{{ __('invoicing.self_billing.pdf.payment', ['iban' => $credit->supplier->bank_iban]) }}
        @endif
        @if ($credit->note)
            <br>{{ $credit->note }}
        @endif
    </p>
</body>
</html>
