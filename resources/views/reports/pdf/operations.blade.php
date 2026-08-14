{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : operations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Operations – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Operations-Auswertung'))

@push('pdf-styles')
<style>
    .grid { width: 100%; }
    .grid td.col { border: 0; width: 50%; vertical-align: top; padding: 0 6px 0 0; }
</style>
@endpush

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')
    @php
        $pct = fn (?float $v) => $v !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %' : '–';
        $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration(abs($minutes), 'clock');
        $num = fn (float $v, int $d = 2) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, $d, withThousandsSeparator: true);
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('Service-Aufträge') }}</div><div class="value">{{ $orders['total'] }}</div></td>
            <td><div class="label">Servicezeit Σ</div><div class="value">{{ $fmtMin($orders['service_minutes']) }}</div></td>
            <td><div class="label">SO Abschluss</div><div class="value">{{ $pct($orders['completion_rate']) }}</div></td>
            <td><div class="label">Tasks (Überfällig)</div><div class="value">{{ $tasks['total'] }} ({{ $tasks['overdue'] }})</div></td>
            <td><div class="label">{{ __('Tasks Abschluss') }}</div><div class="value">{{ $pct($tasks['completion_rate']) }}</div></td>
            <td><div class="label">Touren</div><div class="value">{{ $tours['total'] }} · {{ $num($tours['planned_distance_km'], 0) }} km</div></td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td class="col">
                <h2>{{ __('Service-Aufträge – Status') }}</h2>
                <table class="data">
                    <thead><tr><th>Status</th><th class="right">Anzahl</th></tr></thead>
                    <tbody>
                        @foreach ($orders['by_status'] as $st => $c)
                            <tr><td>{{ $st }}</td><td class="right">{{ $c }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <h2>{{ __('Service-Aufträge – Priorität') }}</h2>
                <table class="data">
                    <thead><tr><th>{{ __('Priorität') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
                    <tbody>
                        @foreach ($orders['by_priority'] as $p => $c)
                            <tr><td>{{ $p }}</td><td class="right">{{ $c }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
            <td class="col">
                <h2>{{ __('Tasks – Status') }}</h2>
                <table class="data">
                    <thead><tr><th>Status</th><th class="right">Anzahl</th></tr></thead>
                    <tbody>
                        @foreach ($tasks['by_status'] as $st => $c)
                            <tr><td>{{ $st }}</td><td class="right">{{ $c }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <h2>{{ __('Tasks – Priorität') }}</h2>
                <table class="data">
                    <thead><tr><th>{{ __('Priorität') }}</th><th class="right">{{ __('Anzahl') }}</th></tr></thead>
                    <tbody>
                        @foreach ($tasks['by_priority'] as $p => $c)
                            <tr><td>{{ $p }}</td><td class="right">{{ $c }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>{{ __('Touren – pro Mitarbeiter') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th class="right">Touren</th>
                <th class="right">Plan-km</th>
                <th class="right">{{ __('Plan-Dauer') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tours['per_user'] as $u)
                <tr>
                    <td>{{ $u['user']->name }}</td>
                    <td class="right">{{ $u['count'] }}</td>
                    <td class="right">{{ $num($u['distance_km'], 1) }} km</td>
                    <td class="right">{{ $fmtMin($u['minutes']) }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Gesamt</td>
                <td class="right">{{ $tours['total'] }}</td>
                <td class="right">{{ $num($tours['planned_distance_km'], 1) }} km</td>
                <td class="right">{{ $fmtMin($tours['planned_minutes']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
