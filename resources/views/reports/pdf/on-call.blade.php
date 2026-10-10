{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : on-call.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Notdienst') . ' – ' . $from . ' ' . __('bis') . ' ' . $to)
@section('pdf-heading', __('Notdienst-Auswertung'))

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> {{ __('bis') }}
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('Bereich') }}: {{ $scope === 'team' ? __('Gesamtes Team') : __('Eigene Bereitschaft') }} ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')
    @php
        $fmt = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
        $pct = fn (float $v) => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v * 100, 1, withThousandsSeparator: true) . ' %';
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('Mitarbeiter') }}</div><div class="value">{{ $totals['users'] }}</div></td>
            <td><div class="label">{{ __('Bereitschaft') }}</div><div class="value">{{ $fmt($totals['shift_minutes']) }}</div></td>
            <td><div class="label">{{ __('Schichten') }}</div><div class="value">{{ $totals['shift_count'] }}</div></td>
            <td><div class="label">{{ __('Einsätze') }}</div><div class="value">{{ $totals['assignment_count'] }} · {{ $fmt($totals['assignment_minutes']) }}</div></td>
            <td><div class="label">{{ __('Aktiv-Anteil') }}</div><div class="value">{{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th class="right">{{ __('Schichten') }}</th>
                <th class="right">{{ __('Bereitschaft') }}</th>
                <th class="right">{{ __('Einsätze') }}</th>
                <th class="right">{{ __('Einsatzzeit') }}</th>
                <th class="right">{{ __('Aktiv-Anteil') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>
                    <td>{{ $r['user']->name }}</td>
                    <td class="right">{{ $r['shift_count'] }}</td>
                    <td class="right">{{ $fmt($r['shift_minutes']) }}</td>
                    <td class="right">{{ $r['assignment_count'] }}</td>
                    <td class="right">{{ $fmt($r['assignment_minutes']) }}</td>
                    <td class="right">{{ $r['ratio'] !== null ? $pct($r['ratio']) : '–' }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td>{{ __('Gesamt') }}</td>
                <td class="right">{{ $totals['shift_count'] }}</td>
                <td class="right">{{ $fmt($totals['shift_minutes']) }}</td>
                <td class="right">{{ $totals['assignment_count'] }}</td>
                <td class="right">{{ $fmt($totals['assignment_minutes']) }}</td>
                <td class="right">{{ $totals['ratio'] !== null ? $pct($totals['ratio']) : '–' }}</td>
            </tr>
        </tbody>
    </table>
@endsection
