{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : report-pdf.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Begleitbericht zum DATEV-Buchungsstapel (Feature 045, MVP-1278).
  Variablen: $batch, $byAccount, $byTaxKey, $reversals, $transmission.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('finance.datev.report.title', ['number' => $batch->batch_no]) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #111; text-transform: uppercase; letter-spacing: 0.06em; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; }
        .kv th { width: 230px; }
        .num { text-align: right; white-space: nowrap; }
        .hash { font-family: DejaVu Sans Mono, monospace; font-size: 9px; word-break: break-all; }
    </style>
</head>
<body>
    <h1>{{ __('finance.datev.report.title', ['number' => $batch->batch_no]) }}</h1>

    <h2>{{ __('finance.datev.report.batch') }}</h2>
    <table class="kv">
        <tr><th>{{ __('finance.datev.field.period') }}</th><td>{{ $batch->period_from?->format('d.m.Y') }} – {{ $batch->period_to?->format('d.m.Y') }}</td></tr>
        <tr><th>{{ __('finance.datev.report.advisor_client') }}</th><td>{{ $batch->advisor_number ?? '—' }} / {{ $batch->client_number ?? '—' }} · SKR {{ $batch->skr }}</td></tr>
        <tr><th>{{ __('finance.datev.report.booking_count') }}</th><td>{{ $batch->booking_count }}@if ($reversals > 0) ({{ __('finance.datev.report.reversals', ['count' => $reversals]) }})@endif</td></tr>
        <tr><th>{{ __('finance.datev.report.total') }}</th><td>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $batch->total_amount, 2) }} EUR</td></tr>
        <tr><th>{{ __('finance.datev.report.finalized') }}</th><td>{{ $batch->finalized_at?->orgTz()->format('d.m.Y H:i') ?? '—' }} · {{ $batch->creator?->name ?? '—' }}</td></tr>
        <tr><th>{{ __('finance.datev.report.transmission') }}</th><td>{{ $transmission->label() }}</td></tr>
        <tr><th>{{ __('finance.datev.report.file_hash') }}</th><td class="hash">{{ $batch->file_hash ?? '—' }}</td></tr>
    </table>

    @foreach (['by_account' => $byAccount, 'by_tax_key' => $byTaxKey] as $section => $rows)
        <h2>{{ __('finance.datev.report.' . $section) }}</h2>
        <table>
            <tr>
                <th>{{ __('finance.datev.report.' . ($section === 'by_account' ? 'account' : 'tax_key')) }}</th>
                <th class="num">{{ __('finance.datev.report.count') }}</th>
                <th class="num">{{ __('finance.datev.report.debit') }}</th>
                <th class="num">{{ __('finance.datev.report.credit') }}</th>
            </tr>
            @foreach ($rows as $key => $row)
                <tr>
                    <td>{{ $key }}</td>
                    <td class="num">{{ $row['count'] }}</td>
                    <td class="num">{{ $row['soll']->format() }}</td>
                    <td class="num">{{ $row['haben']->format() }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <h2>{{ __('finance.datev.report.history') }}</h2>
    <table>
        @foreach ($batch->journal as $event)
            <tr>
                <td style="width: 140px">{{ $event->created_at?->orgTz()->format('d.m.Y H:i') }}</td>
                <td>{{ $event->label() }}@if (! empty($event->payload['note'])) — {{ $event->payload['note'] }}@endif</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
