@extends('reports.pdf.layout')

@section('pdf-title', 'Kundenwert')
@section('pdf-heading', 'Kundenwert')

@section('pdf-table')
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        {{ __('Erlös gesamt') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['totalRevenue'], 2, withThousandsSeparator: true) }} € ·
        {{ __('Top-5-Anteil') }}: {{ $concentration['top5Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top5Share'], 1) . ' %' : '–' }} ·
        HHI: {{ $concentration['hhi'] ?? '–' }}
    </p>

    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>{{ __('Kunde') }}</th>
                <th>{{ __('Segment') }}</th>
                <th class="num">{{ __('Tage seit Leistung') }}</th>
                <th class="num">{{ __('Aktivitätstage') }}</th>
                <th class="num">{{ __('Erlös') }} (€)</th>
                <th class="num">{{ __('Fakturiert') }} (€)</th>
                <th class="num">R</th>
                <th class="num">F</th>
                <th class="num">M</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['customerName'] }}</td>
                    <td>{{ $segmentLabels[$row['segment']] ?? $row['segment'] }}</td>
                    <td class="num">{{ $row['recencyDays'] ?? '–' }}</td>
                    <td class="num">{{ $row['frequencyDays'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['revenue'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['invoiced'] > 0 ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['invoiced'], 2, withThousandsSeparator: true) : '–' }}</td>
                    <td class="num">{{ $row['r'] ?? '–' }}</td>
                    <td class="num">{{ $row['f'] ?? '–' }}</td>
                    <td class="num">{{ $row['m'] ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
