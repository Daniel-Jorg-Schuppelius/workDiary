<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Fuhrpark – {{ $from }} bis {{ $to }}</title>
<style>
    @page { margin: 14mm 12mm; }
    body  { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    h1    { font-size: 14pt; margin: 0 0 4pt 0; }
    .meta { font-size: 9pt; color: #555; margin-bottom: 10pt; }
    .kpis { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
    .kpis td { padding: 4pt 6pt; border: 1px solid #ccc; background: #f7f7f7; }
    .kpis .label { font-size: 8pt; color: #666; }
    .kpis .value { font-size: 11pt; font-weight: bold; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border-bottom: 1px solid #ccc; padding: 3pt 4pt; vertical-align: top; }
    table.data th { background: #f3f3f3; text-align: left; font-size: 8.5pt; }
    .right { text-align: right; }
    .totals td { border-top: 2px solid #333; font-weight: bold; }
</style>
</head>
<body>
@php
    $money = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
    $num   = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
@endphp

<h1>Fuhrpark-Auswertung</h1>
<div class="meta">
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamter Fuhrpark' : 'Eigene Fahrten' }} ·
    Erstellt: {{ now()->format('d.m.Y H:i') }}
</div>

<table class="kpis">
    <tr>
        <td><div class="label">Fahrzeuge</div><div class="value">{{ $totals['vehicles'] }}</div></td>
        <td><div class="label">Σ km</div><div class="value">{{ $num($totals['km'], 1) }}</div></td>
        <td><div class="label">Fahrten</div><div class="value">{{ $totals['trip_count'] }}</div></td>
        <td><div class="label">Tankungen / Ladungen</div><div class="value">{{ $totals['fuel_count'] }}</div></td>
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
                    @if ($r['vehicle']->label)<br><span style="font-size:8pt;color:#666">{{ $r['vehicle']->label }}</span>@endif
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
                <td class="right">{{ $r['last_odometer'] !== null ? number_format((int) $r['last_odometer'], 0, ',', '.') : '–' }}</td>
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

</body>
</html>
