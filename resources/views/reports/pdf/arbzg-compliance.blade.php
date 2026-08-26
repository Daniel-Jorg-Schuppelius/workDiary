{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : arbzg-compliance.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('compliance.report.title') . ' – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('compliance.report.title'))

@push('pdf-styles')
<style>
    .err  { color: #b30000; font-weight: bold; }
    .warn { color: #9a6a00; font-weight: bold; }
</style>
@endpush

@section('pdf-meta')
    {{ __('compliance.report.csv.date') }}:
    <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> –
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('compliance.report.kpi.total') }}: <strong>{{ $summary['total'] }}</strong> ·
    {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')
    @php
        // Einheit je Regel-Art (Minuten/Tage/Anzahl) — zentral im Finding-Objekt.
        $fmtVal = fn (string $kind, int $value): string => \App\Services\Compliance\AttendanceComplianceFinding::formatValue($kind, $value);
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">{{ __('compliance.report.kpi.total') }}</div><div class="value">{{ $summary['total'] }}</div></td>
            @foreach ($kinds as $kind)
                <td><div class="label">{{ __('compliance.report.kind.' . $kind) }}</div><div class="value">{{ $summary['by_kind'][$kind] ?? 0 }}</div></td>
            @endforeach
        </tr>
    </table>

    @forelse ($rows as $r)
        <h2>{{ $r['user']->name }}</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('compliance.report.col.date') }}</th>
                    <th>{{ __('compliance.report.col.kind') }}</th>
                    <th class="right">{{ __('compliance.report.col.value') }}</th>
                    <th class="right">{{ __('compliance.report.col.threshold') }}</th>
                    <th>{{ __('compliance.report.col.severity') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($r['findings'] as $f)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($f['date'])->fdate() }}@if ($f['corrected']) · {{ __('compliance.report.corrected') }}@endif</td>
                        <td>{{ __('compliance.report.kind.' . $f['kind']) }}</td>
                        <td class="right">{{ $fmtVal((string) $f['kind'], (int) $f['value']) }}</td>
                        <td class="right">{{ $fmtVal((string) $f['kind'], (int) $f['threshold']) }}</td>
                        <td class="{{ $f['severity'] === 'error' ? 'err' : 'warn' }}">{{ __('compliance.report.severity.' . $f['severity']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="text-align:center; padding:12px; color:#888;">{{ __('compliance.report.empty') }}</p>
    @endforelse
@endsection
