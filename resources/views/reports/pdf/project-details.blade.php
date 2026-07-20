@extends('reports.pdf.layout')

@section('pdf-title', 'Projekt-Details ' . $project->name . ' – ' . $year)

@section('pdf-heading')
    Projekt: {{ $project->name }}@if ($project->customer) <span style="font-weight:normal;color:#555;">– {{ $project->customer->name }}</span>@endif
@endsection

@push('pdf-styles')
<style>
    table.data { margin-bottom: 8px; }
</style>
@endpush

@section('pdf-meta')
    Jahr: <strong>{{ $year }}</strong> · Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    <h2>Monatswerte</h2>
    <table class="data">
        <thead>
            <tr><th>{{ __('Monat') }}</th><th class="right">{{ __('Stunden') }}</th><th class="right">{{ __('Erlös') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($monthMatrix as $idx => $row)
                @php
                    $h = intdiv((int) $row['minutes'], 60);
                    $m = (int) $row['minutes'] % 60;
                @endphp
                <tr>
                    <td>{{ $monthLabels[$idx] ?? $idx }}</td>
                    <td class="right">{{ $row['minutes'] > 0 ? sprintf('%d:%02d', $h, $m) : '–' }}</td>
                    <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php
                $hY = intdiv((int) $yearMinutes, 60);
                $mY = (int) $yearMinutes % 60;
            @endphp
            <tr><td>Gesamt</td><td class="right">{{ $hY }}:{{ str_pad((string) $mY, 2, '0', STR_PAD_LEFT) }}</td><td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $yearRate, 2, withThousandsSeparator: true) }} €</td></tr>
        </tfoot>
    </table>

    @if (count($byUser) > 0)
        <h2>{{ __('Aufteilung pro Mitarbeiter') }}</h2>
        <table class="data">
            <thead>
                <tr><th>{{ __('Mitarbeiter') }}</th><th class="right">{{ __('Stunden') }}</th><th class="right">{{ __('Erlös') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($byUser as $uid => $row)
                    @php
                        $h = intdiv((int) $row['minutes'], 60);
                        $m = (int) $row['minutes'] % 60;
                    @endphp
                    <tr>
                        <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                        <td class="right">{{ $h }}:{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
