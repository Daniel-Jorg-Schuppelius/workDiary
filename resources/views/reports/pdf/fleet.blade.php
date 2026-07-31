@extends('reports.pdf.layout')

@section('pdf-title', 'Fuhrpark – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Fuhrpark-Auswertung'))

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamter Fuhrpark' : 'Eigene Fahrten' }} ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')
    @php
        $money = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true) . ' €';
        $num   = fn (float $v, int $d = 2) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, $d, withThousandsSeparator: true);
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Fahrzeuge</div><div class="value">{{ $totals['vehicles'] }}</div></td>
            <td><div class="label">Σ km</div><div class="value">{{ $num($totals['km'], 1) }}</div></td>
            <td><div class="label">Fahrten</div><div class="value">{{ $totals['trip_count'] }}</div></td>
            <td><div class="label">{{ __('Tankungen / Ladungen') }}</div><div class="value">{{ $totals['fuel_count'] }}</div></td>
            <td><div class="label">Energiekosten</div><div class="value">{{ $money($totals['energy_cost']) }}</div></td>
            <td><div class="label">Ø €/km</div><div class="value">{{ $totals['avg_cost_per_km'] !== null ? $num($totals['avg_cost_per_km'], 3) . ' €' : '–' }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Fahrzeug</th>
                <th>Antrieb</th>
                <th class="right">Fahrten</th>
                <th class="right">km</th>
                <th class="right">Erstattung</th>
                <th class="right">Tankungen</th>
                <th class="right">Liter</th>
                <th class="right">kWh</th>
                <th class="right">Energiekosten</th>
                <th class="right">€/km</th>
                <th class="right">Tachostand</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>
                        <strong>{{ $r['vehicle']->license_plate }}</strong>
                        @if ($r['vehicle']->label)<br><span class="small">{{ $r['vehicle']->label }}</span>@endif
                    </td>
                    <td>{{ $r['vehicle']->propulsion->label() }}</td>
                    <td class="right">{{ $r['trip_count'] }}</td>
                    <td class="right">{{ $num($r['km'], 1) }}</td>
                    <td class="right">{{ $money($r['reimbursement']) }}</td>
                    <td class="right">{{ $r['fuel_count'] }}</td>
                    <td class="right">{{ $r['liters'] > 0 ? $num($r['liters'], 2) : '–' }}</td>
                    <td class="right">{{ $r['kwh'] > 0 ? $num($r['kwh'], 2) : '–' }}</td>
                    <td class="right">{{ $money($r['energy_cost']) }}</td>
                    <td class="right">{{ $r['cost_per_km'] !== null ? $num($r['cost_per_km'], 3) . ' €' : '–' }}</td>
                    <td class="right">{{ $r['last_odometer'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $r['last_odometer'], 0, withThousandsSeparator: true) : '–' }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="2">Gesamt</td>
                <td class="right">{{ $totals['trip_count'] }}</td>
                <td class="right">{{ $num($totals['km'], 1) }}</td>
                <td class="right">{{ $money($totals['reimbursement']) }}</td>
                <td class="right">{{ $totals['fuel_count'] }}</td>
                <td class="right">{{ $num($totals['liters'], 2) }}</td>
                <td class="right">{{ $num($totals['kwh'], 2) }}</td>
                <td class="right">{{ $money($totals['energy_cost']) }}</td>
                <td class="right">{{ $totals['avg_cost_per_km'] !== null ? $num($totals['avg_cost_per_km'], 3) . ' €' : '–' }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
@endsection
