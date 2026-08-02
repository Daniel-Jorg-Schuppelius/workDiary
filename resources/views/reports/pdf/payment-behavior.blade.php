@extends('reports.pdf.layout')

@section('pdf-title', 'Zahlungsverhalten')
@section('pdf-heading', 'Zahlungsverhalten & Forderungstrend')

@section('pdf-table')
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        DSO: {{ $kpis['dso'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kpis['dso'], 1) . ' ' . __('Tage') : '–' }} ·
        {{ __('Ø Zahldauer (Tage)') }}: {{ $kpis['avgPayDays'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kpis['avgPayDays'], 1) : '–' }} ·
        {{ __('Pünktlich bezahlt') }}: {{ $kpis['onTimeShare'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kpis['onTimeShare'], 1) . ' %' : '–' }} ·
        {{ __('Überfällig') }}: {{ $kpis['overdueCount'] }} ({{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($kpis['overdueTotal'], 2, withThousandsSeparator: true) }} €)
    </p>

    @include('reports.pdf.charts._chart')

    <h2>{{ __('Ø Verzugstage je Kunde (Top 10)') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Kunde') }}</th>
                <th class="num">{{ __('Ø Verzugstage') }}</th>
                <th class="num">{{ __('Rechnungen') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($delayTop as $row)
                <tr>
                    <td>{{ $row['customerName'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['avgDelay'], 1) }}</td>
                    <td class="num">{{ $row['invoices'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Überfällige offene Rechnungen (Top 15)') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Rechnung') }}</th>
                <th>{{ __('Kunde') }}</th>
                <th class="num">{{ __('Fällig am') }}</th>
                <th class="num">{{ __('Tage überfällig') }}</th>
                <th class="num">{{ __('Betrag') }} (€)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($overdue as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['customerName'] }}</td>
                    <td class="num">{{ $row['dueOn'] }}</td>
                    <td class="num">{{ $row['daysOverdue'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['total'], 2, withThousandsSeparator: true) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
