{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : driving-time-evidence.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('compliance.driving.title') . ' – ' . $from . ' bis ' . $to)
@section('pdf-heading', __('compliance.driving.title'))

@push('pdf-styles')
<style>
    .finding { color: #b30000; }
</style>
@endpush

@section('pdf-meta')
    {{ __('compliance.driving.csv.date') }}:
    <strong>{{ \Carbon\Carbon::parse($from)->fdate() }}</strong> –
    <strong>{{ \Carbon\Carbon::parse($to)->fdate() }}</strong> ·
    {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @php
        $clock = fn (int $minutes): string => \App\Support\Formats::duration($minutes, 'clock');
        $byDriver = [];
        foreach ($rows as $row) {
            $byDriver[$row['driver']][] = $row;
        }
    @endphp

    <p style="font-size:10px; color:#555;">{{ __('compliance.driving.thresholds_note') }} {{ __('compliance.driving.disclaimer') }}</p>

    @forelse ($byDriver as $driver => $driverRows)
        <h2>{{ $driver }}@if ($driverRows[0]['personnel_number'] !== '') · {{ $driverRows[0]['personnel_number'] }}@endif</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('compliance.driving.csv.date') }}</th>
                    <th>{{ __('compliance.driving.csv.vehicles') }}</th>
                    <th>{{ __('compliance.driving.csv.start') }}</th>
                    <th>{{ __('compliance.driving.csv.end') }}</th>
                    <th class="right">{{ __('compliance.driving.csv.driving') }}</th>
                    <th class="right">{{ __('compliance.driving.csv.longest_stint') }}</th>
                    <th class="right">{{ __('compliance.driving.csv.breaks') }}</th>
                    <th class="right">{{ __('compliance.driving.csv.rest_before') }}</th>
                    <th>{{ __('compliance.driving.csv.findings') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($driverRows as $r)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($r['date'])->fdate() }}</td>
                        <td>{{ $r['vehicles'] }}</td>
                        <td>{{ $r['start'] }}</td>
                        <td>{{ $r['end'] }}</td>
                        <td class="right">{{ $clock($r['driving']) }}</td>
                        <td class="right">{{ $clock($r['longest_stint']) }}</td>
                        <td class="right">{{ $r['breaks'] }}</td>
                        <td class="right">{{ $r['rest_before'] === null ? '—' : $clock($r['rest_before']) }}</td>
                        <td class="{{ $r['findings'] !== '' ? 'finding' : '' }}">{{ $r['findings'] !== '' ? $r['findings'] : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p style="text-align:center; padding:12px; color:#888;">{{ __('compliance.report.empty') }}</p>
    @endforelse
@endsection
