@extends('reports.pdf.layout')

@section('pdf-title', __('Urlaub & Flex') . ' – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Urlaub & Flex'))

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    @php
        $fmtMin = function (int $minutes): string {
            $sign = $minutes < 0 ? '-' : ($minutes > 0 ? '+' : '');
            $abs = abs($minutes);
            return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
        };
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Mitarbeiter</div><div class="value">{{ $totals['users'] }}</div></td>
            <td><div class="label">Urlaub (Werktage)</div><div class="value">{{ $totals['vacation_days'] }}</div></td>
            <td><div class="label">Krank</div><div class="value">{{ $totals['sick_days'] }}</div></td>
            <td><div class="label">{{ __('Sonder / Unbezahlt') }}</div><div class="value">{{ $totals['special_days'] }} / {{ $totals['unpaid_days'] }}</div></td>
            <td><div class="label">Ausstehend</div><div class="value">{{ $totals['pending_days'] }}</div></td>
            <td><div class="label">Flex Δ</div><div class="value {{ $totals['flex_change_minutes'] < 0 ? 'neg' : 'pos' }}">{{ $fmtMin($totals['flex_change_minutes']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th class="right">Urlaub</th>
                <th class="right">Krank</th>
                <th class="right">Sonder</th>
                <th class="right">Unbezahlt</th>
                <th class="right">Ausstehend</th>
                <th class="right">{{ __('Anspruch :year', ['year' => $balanceYear]) }}</th>
                <th class="right">{{ __('Rest :year', ['year' => $balanceYear]) }}</th>
                <th class="right">Flex Δ</th>
                <th class="right">{{ __('Flex-Saldo') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['user']->name }}</td>
                    <td class="right">{{ $r['vacation_days'] }}</td>
                    <td class="right">{{ $r['sick_days'] }}</td>
                    <td class="right">{{ $r['special_days'] }}</td>
                    <td class="right">{{ $r['unpaid_days'] }}</td>
                    <td class="right">{{ $r['pending_days'] }}</td>
                    <td class="right">{{ ($r['entitled_total_days'] ?? null) !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($r['entitled_total_days'], 1, withThousandsSeparator: true) : '–' }}</td>
                    <td class="right {{ ($r['remaining_days'] ?? null) !== null && $r['remaining_days'] < 0 ? 'neg' : '' }}">{{ ($r['remaining_days'] ?? null) !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($r['remaining_days'], 1, withThousandsSeparator: true) : '–' }}</td>
                    <td class="right {{ $r['flex_change_minutes'] < 0 ? 'neg' : ($r['flex_change_minutes'] > 0 ? 'pos' : '') }}">{{ $fmtMin($r['flex_change_minutes']) }}</td>
                    <td class="right">{{ $r['flex_balance_minutes'] !== null ? $fmtMin($r['flex_balance_minutes']) : '–' }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Gesamt</td>
                <td class="right">{{ $totals['vacation_days'] }}</td>
                <td class="right">{{ $totals['sick_days'] }}</td>
                <td class="right">{{ $totals['special_days'] }}</td>
                <td class="right">{{ $totals['unpaid_days'] }}</td>
                <td class="right">{{ $totals['pending_days'] }}</td>
                <td class="right">–</td>
                <td class="right">–</td>
                <td class="right">{{ $fmtMin($totals['flex_change_minutes']) }}</td>
                <td class="right">{{ $fmtMin($totals['flex_balance_minutes']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
