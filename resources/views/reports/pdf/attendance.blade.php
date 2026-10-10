{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : attendance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Anwesenheit') . ' – ' . $from . ' ' . __('bis') . ' ' . $to)
@section('pdf-heading', __('Anwesenheits-Auswertung'))

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> {{ __('bis') }}
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('Bereich') }}: {{ $scope === 'team' ? __('Gesamtes Team') : __('Eigene') }} ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    @php
        $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
        $varClass = fn (int $v) => $v < 0 ? 'neg' : ($v > 0 ? 'pos' : '');
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('Soll') }}</div><div class="value">{{ $fmtMin($totals['target']) }}</div></td>
            <td><div class="label">{{ __('Anwesend') }}</div><div class="value">{{ $fmtMin($totals['attendance']) }}</div></td>
            <td><div class="label">{{ __('Gebucht') }}</div><div class="value">{{ $fmtMin($totals['time_entry']) }}</div></td>
            <td><div class="label">{{ __('Saldo') }}</div><div class="value {{ $varClass($totals['variance']) }}">{{ $fmtMin($totals['variance']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Mitarbeiter') }}</th>
                <th class="right">{{ __('Arbeitstage') }}</th>
                <th class="right">{{ __('Soll') }}</th>
                <th class="right">{{ __('Anwesend') }}</th>
                <th class="right">{{ __('Gebucht') }}</th>
                <th class="right">{{ __('Saldo') }}</th>
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
                    <td>{{ __('Gesamt') }}</td>
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
