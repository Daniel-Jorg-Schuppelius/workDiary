@extends('reports.pdf.layout')

@section('pdf-title', 'Coverage – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('Coverage / Soll-Ist-Besetzung'))

@section('pdf-meta')
    Zeitraum: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $pct = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %';
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Schichttypen</div><div class="value">{{ $totals['shift_types'] }}</div></td>
            <td><div class="label">Soll (Personentage)</div><div class="value">{{ $totals['required'] }}</div></td>
            <td><div class="label">Ist (Personentage)</div><div class="value">{{ $totals['scheduled'] }}</div></td>
            <td><div class="label">Differenz</div><div class="value {{ $totals['gap'] < 0 ? 'neg' : 'pos' }}">{{ $totals['gap'] > 0 ? '+' : '' }}{{ $totals['gap'] }}</div></td>
            <td><div class="label">{{ __('Erfüllung') }}</div><div class="value">{{ $totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–' }}</div></td>
            <td><div class="label">Tage unter</div><div class="value {{ $totals['days_under'] > 0 ? 'neg' : '' }}">{{ $totals['days_under'] }}</div></td>
        </tr>
    </table>

    <h2>{{ __('Pro Schichttyp') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Schichttyp') }}</th>
                <th class="right">{{ __('Soll') }}</th>
                <th class="right">{{ __('Ist') }}</th>
                <th class="right">{{ __('Differenz') }}</th>
                <th class="right">{{ __('Erfüllung') }}</th>
                <th class="right">{{ __('Tage unter') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['shiftType']->name }}@if ($r['shiftType']->abbreviation) <span style="color:#666">({{ $r['shiftType']->abbreviation }})</span>@endif</td>
                    <td class="right">{{ $r['required'] }}</td>
                    <td class="right">{{ $r['scheduled'] }}</td>
                    <td class="right {{ $r['gap'] < 0 ? 'neg' : ($r['gap'] > 0 ? 'pos' : '') }}">{{ $r['gap'] > 0 ? '+' : '' }}{{ $r['gap'] }}</td>
                    <td class="right">{{ $r['fill_rate'] !== null ? $pct($r['fill_rate']) : '–' }}</td>
                    <td class="right {{ $r['days_under'] > 0 ? 'neg' : '' }}">{{ $r['days_under'] }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>Gesamt</td>
                <td class="right">{{ $totals['required'] }}</td>
                <td class="right">{{ $totals['scheduled'] }}</td>
                <td class="right">{{ $totals['gap'] > 0 ? '+' : '' }}{{ $totals['gap'] }}</td>
                <td class="right">{{ $totals['fill_rate'] !== null ? $pct($totals['fill_rate']) : '–' }}</td>
                <td class="right">{{ $totals['days_under'] }}</td>
            </tr>
        </tbody>
    </table>

    @if (! empty($underfilled))
        <h2>Tage mit Unterdeckung ({{ count($underfilled) }})</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('Datum') }}</th>
                    <th>{{ __('Schichttyp') }}</th>
                    <th class="right">{{ __('Soll') }}</th>
                    <th class="right">{{ __('Ist') }}</th>
                    <th class="right">{{ __('Lücke') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($underfilled as $u)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($u['date'])->translatedFormat('D, d.m.Y') }}</td>
                        <td>{{ $u['shiftType']->name }}</td>
                        <td class="right">{{ $u['required'] }}</td>
                        <td class="right">{{ $u['scheduled'] }}</td>
                        <td class="right neg">{{ $u['gap'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
