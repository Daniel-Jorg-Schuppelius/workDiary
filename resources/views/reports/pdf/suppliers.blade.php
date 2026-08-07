@extends('reports.pdf.layout')

@section('pdf-title', 'Lieferantenanalyse')
@section('pdf-heading', 'Lieferantenanalyse')

@section('pdf-table')
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        {{ __('Ausgaben gesamt') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['totalSpend'], 2, withThousandsSeparator: true) }} € ·
        {{ __('Top-5-Anteil') }}: {{ $concentration['top5Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top5Share'], 1) . ' %' : '–' }} ·
        HHI: {{ $concentration['hhi'] ?? '–' }}
    </p>

    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>{{ __('Lieferant') }}</th>
                <th class="num">{{ __('Ausgaben') }} (€)</th>
                <th class="num">{{ __('Belege') }}</th>
                <th class="num">{{ __('Ø Beleg') }} (€)</th>
                <th class="num">{{ __('Offener Betrag') }} (€)</th>
                <th class="num">{{ __('Tage seit Beleg') }}</th>
                <th class="num">{{ __('Trend %') }}</th>
                @if ($withProcurement)
                    <th class="num">{{ __('Bestellungen') }}</th>
                    <th class="num">{{ __('Offene Bestellungen') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['supplierName'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['spend'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['voucherCount'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['avgVoucher'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['openAmount'] > 0 ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['openAmount'], 2, withThousandsSeparator: true) : '–' }}</td>
                    <td class="num">{{ $row['recencyDays'] ?? '–' }}</td>
                    <td class="num">{{ $row['trendPct'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['trendPct'], 1) . ' %' : '–' }}</td>
                    @if ($withProcurement)
                        <td class="num">{{ $row['orderCount'] ?? 0 }}</td>
                        <td class="num">{{ $row['openOrderCount'] ?? 0 }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
