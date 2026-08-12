@extends('reports.pdf.layout')

@section('pdf-title', __('reporting.allocations.title') . ' – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('reporting.allocations.title'))

@section('pdf-meta')
    {{ __('Zeitraum') }}: <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> bis
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ __('reporting.allocations.total') }}: {{ \App\Support\Formats::duration($totalMinutes, 'clock') }} ·
    {{ __('Erstellt') }}: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $fmtMin = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
    @endphp

    @forelse ($groups as $group)
        <h3>{{ $group['label'] }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('reporting.allocations.col_target') }}</th>
                    <th class="right">{{ __('reporting.allocations.col_minutes') }}</th>
                    <th class="right">{{ __('reporting.allocations.col_entries') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="right">{{ $fmtMin($row['minutes']) }}</td>
                        <td class="right">{{ $row['entries'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="text-align:center; padding:12px; color:#888;">{{ __('reporting.allocations.empty') }}</p>
    @endforelse

    <p style="margin-top:10px; font-size:9px; color:#777;">{{ __('reporting.allocations.note') }}</p>
@endsection
