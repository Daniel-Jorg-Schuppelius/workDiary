@extends('reports.pdf.layout')

@section('pdf-title', 'Mein Monat – ' . $monthLabel)
@section('pdf-heading', 'Mein Monat – ' . $monthLabel)

@push('pdf-styles')
<style>
    table.day-table { margin-bottom: 12px; }
    .day-header th { background: #eef; font-weight: bold; }
    .day-header.sun th { color: #c00; }
    .month-total { margin-top: 6px; }
    .month-total td { border: 0; padding: 2px 5px; }
    .badge { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 10px; background: #ddd; }
</style>
@endpush

@section('pdf-meta')
    Erstellt: {{ now()->fdatetime() }} – Nutzer: {{ auth()->user()?->name }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    @forelse ($byDay as $date => $row)
        @php
            $h = intdiv((int) $row['minutes'], 60);
            $m = (int) $row['minutes'] % 60;
            $isSunday = \Carbon\Carbon::parse($date)->isSunday();
        @endphp
        <table class="data day-table">
            <thead>
                <tr class="day-header{{ $isSunday ? ' sun' : '' }}">
                    <th colspan="4">{{ \Carbon\Carbon::parse($date)->locale(app()->getLocale())->isoFormat('dddd, DD.MM.YYYY') }}</th>
                    <th class="right">{{ $h }}:{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }} h</th>
                    <th class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</th>
                </tr>
                <tr>
                    <th style="width: 8%">Start</th>
                    <th style="width: 8%">Ende</th>
                    <th style="width: 10%">Art</th>
                    <th>{{ __('Projekt / Aufgabe / Beschreibung') }}</th>
                    <th class="right" style="width: 12%">Dauer</th>
                    <th class="right" style="width: 14%">{{ __('Erlös') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($row['entries'] as $e)
                    @php
                        $eh = intdiv((int) $e->minutes, 60);
                        $em = (int) $e->minutes % 60;
                    @endphp
                    <tr>
                        <td>{{ $e->started_at ? \Carbon\Carbon::parse((string) $e->started_at)->ftime() : '' }}</td>
                        <td>{{ $e->ended_at ? \Carbon\Carbon::parse((string) $e->ended_at)->ftime() : '' }}</td>
                        <td><span class="badge">{{ $e->kind?->label() ?? '' }}</span></td>
                        <td>
                            @if ($e->project)
                                <strong>{{ $e->project->name }}</strong>@if ($e->project->customer) <span class="small">– {{ $e->project->customer->name }}</span>@endif<br>
                            @endif
                            @if ($e->task)<span class="small">{{ $e->task->title }}</span><br>@endif
                            {{ $e->description }}
                        </td>
                        <td class="right">{{ $eh }}:{{ str_pad((string) $em, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($e->rate?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p>{{ __('Keine Einträge im gewählten Monat.') }}</p>
    @endforelse

    @php
        $hM = intdiv((int) $monthMinutes, 60);
        $mM = (int) $monthMinutes % 60;
    @endphp
    <table class="month-total">
        <tr>
            <td class="right" style="width: 70%;"><strong>Monat gesamt:</strong></td>
            <td class="right" style="width: 15%;"><strong>{{ $hM }}:{{ str_pad((string) $mM, 2, '0', STR_PAD_LEFT) }} h</strong></td>
            <td class="right" style="width: 15%;"><strong>{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $monthRate, 2, withThousandsSeparator: true) }} €</strong></td>
        </tr>
    </table>
@endsection
