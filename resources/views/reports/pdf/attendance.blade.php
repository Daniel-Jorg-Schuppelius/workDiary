@extends('reports.pdf.layout')

@section('pdf-title', 'Anwesenheit – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Anwesenheits-Auswertung'))

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Bereich: {{ $scope === 'team' ? 'Gesamtes Team' : 'Eigene' }} ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    @php
        $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
        $varClass = fn (int $v) => $v < 0 ? 'neg' : ($v > 0 ? 'pos' : '');
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Soll</div><div class="value">{{ $fmtMin($totals['target']) }}</div></td>
            <td><div class="label">Anwesend</div><div class="value">{{ $fmtMin($totals['attendance']) }}</div></td>
            <td><div class="label">Gebucht</div><div class="value">{{ $fmtMin($totals['time_entry']) }}</div></td>
            <td><div class="label">Saldo</div><div class="value {{ $varClass($totals['variance']) }}">{{ $fmtMin($totals['variance']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                <th class="right">Arbeitstage</th>
                <th class="right">Soll</th>
                <th class="right">Anwesend</th>
                <th class="right">Gebucht</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['user']->name }}</td>
                    <td class="right">{{ $r['workdays'] }}</td>
                    <td class="right">{{ $fmtMin($r['target_minutes']) }}</td>
                    <td class="right">{{ $fmtMin($r['attendance_minutes']) }}</td>
                    <td class="right">{{ $fmtMin($r['time_entry_minutes']) }}</td>
                    <td class="right {{ $varClass($r['variance']) }}">{{ $fmtMin($r['variance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center; padding:12px; color:#888;">{{ __('Keine Daten.') }}</td></tr>
            @endforelse
            @if (! empty($rows))
                <tr class="totals">
                    <td>Gesamt</td>
                    <td></td>
                    <td class="right">{{ $fmtMin($totals['target']) }}</td>
                    <td class="right">{{ $fmtMin($totals['attendance']) }}</td>
                    <td class="right">{{ $fmtMin($totals['time_entry']) }}</td>
                    <td class="right {{ $varClass($totals['variance']) }}">{{ $fmtMin($totals['variance']) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
