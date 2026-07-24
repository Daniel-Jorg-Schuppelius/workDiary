{{--
  Created on   : Thu Jul 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Anwesenheitsnachweis (Feature 098, Excel-„Druckvorlage"): wird vom
     DocumentDesignRenderer in den Firmenbogen eingebettet — nur Inhalt,
     kein <html>-Gerüst. Datenquelle: CustomerAccountStatementService::monthData. --}}

@php
    $money = fn ($v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, 2, withThousandsSeparator: true) . ' €';
    $hours = fn (int $m) => sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    $statement = $statement ?? null;
@endphp

<h1 style="font-size: 16pt; margin-bottom: 2mm;">{{ __('customer-billing.statement_pdf_title') }} — {{ $statement->periodLabel() }}</h1>
<p style="font-size: 9pt; color: #555; margin-bottom: 6mm;">
    {{ $agreement->customer?->name }}
    @unless ($locked)
        · {{ __('customer-billing.provisional') }}
    @endunless
</p>

<table style="width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 6mm;">
    <thead>
        <tr>
            <th style="text-align: left; border-bottom: 1px solid #999; padding: 1mm 2mm;">{{ __('customer-billing.weekday') }}</th>
            <th style="text-align: left; border-bottom: 1px solid #999; padding: 1mm 2mm;">{{ __('customer-billing.date') }}</th>
            <th style="text-align: left; border-bottom: 1px solid #999; padding: 1mm 2mm;">{{ __('customer-billing.reason') }}</th>
            <th style="text-align: left; border-bottom: 1px solid #999; padding: 1mm 2mm;">{{ __('customer-billing.start') }}</th>
            <th style="text-align: left; border-bottom: 1px solid #999; padding: 1mm 2mm;">{{ __('customer-billing.end') }}</th>
            <th style="text-align: right; border-bottom: 1px solid #999; padding: 1mm 2mm;">{{ __('customer-billing.duration') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td style="padding: 1mm 2mm; border-bottom: 1px solid #ddd;">{{ $row['weekday'] }}</td>
                <td style="padding: 1mm 2mm; border-bottom: 1px solid #ddd;">{{ \Illuminate\Support\Carbon::parse($row['date'])->fdate() }}</td>
                <td style="padding: 1mm 2mm; border-bottom: 1px solid #ddd;">{{ $row['category'] }}</td>
                <td style="padding: 1mm 2mm; border-bottom: 1px solid #ddd;">{{ $row['start'] }}</td>
                <td style="padding: 1mm 2mm; border-bottom: 1px solid #ddd;">{{ $row['end'] }}</td>
                <td style="padding: 1mm 2mm; border-bottom: 1px solid #ddd; text-align: right;">{{ $hours((int) $row['minutes']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding: 2mm;">{{ __('customer-billing.no_entries') }}</td></tr>
        @endforelse
    </tbody>
</table>

<table style="border-collapse: collapse; font-size: 10pt; margin-left: auto;">
    <tr>
        <td style="padding: 1mm 4mm; text-align: right; color: #555;">{{ __('customer-billing.gross_value') }} ({{ $hours((int) $statement->total_minutes) }} h)</td>
        <td style="padding: 1mm 2mm; text-align: right;">{{ $money($statement->gross_value) }}</td>
    </tr>
    <tr>
        <td style="padding: 1mm 4mm; text-align: right; color: #555;">{{ __('customer-billing.payments_total') }}</td>
        <td style="padding: 1mm 2mm; text-align: right;">{{ $money($statement->payments_total) }}</td>
    </tr>
    <tr>
        <td style="padding: 1mm 4mm; text-align: right; color: #555;">{{ __('customer-billing.carry_in') }}</td>
        <td style="padding: 1mm 2mm; text-align: right;">{{ $money($statement->carry_in) }}</td>
    </tr>
    <tr>
        <td style="padding: 1mm 4mm; text-align: right; font-weight: bold; border-top: 1px solid #999;">{{ __('customer-billing.balance') }}</td>
        <td style="padding: 1mm 2mm; text-align: right; font-weight: bold; border-top: 1px solid #999;">{{ $money($statement->balance) }}</td>
    </tr>
</table>
