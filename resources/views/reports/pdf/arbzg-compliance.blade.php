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
        $fmtMin = function (int $minutes): string {
            $sign = $minutes < 0 ? '-' : '';
            $abs = abs($minutes);
            return $sign . intdiv($abs, 60) . ':' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . ' h';
        };
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
                        <td class="right">{{ $fmtMin((int) $f['value']) }}</td>
                        <td class="right">{{ $fmtMin((int) $f['threshold']) }}</td>
                        <td class="{{ $f['severity'] === 'error' ? 'err' : 'warn' }}">{{ __('compliance.report.severity.' . $f['severity']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="text-align:center; padding:12px; color:#888;">{{ __('compliance.report.empty') }}</p>
    @endforelse
@endsection
