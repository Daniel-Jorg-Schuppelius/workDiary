{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : project-details.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
    @include('reports.pdf.charts._chart')

    <h2>Monatswerte</h2>
    <table class="data">
        <thead>
            <tr><th>{{ __('Monat') }}</th><th class="right">{{ __('Stunden') }}</th><th class="right">{{ __('Erlös') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($monthMatrix as $idx => $row)
                <tr>
                    <td>{{ $monthLabels[$idx] ?? $idx }}</td>
                    <td class="right">{{ $row['minutes'] > 0 ? \App\Support\Formats::duration((int) $row['minutes'], 'clock', withUnit: false) : '–' }}</td>
                    <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td>Gesamt</td><td class="right">{{ \App\Support\Formats::duration((int) $yearMinutes, 'clock', withUnit: false) }}</td><td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $yearRate, 2, withThousandsSeparator: true) }} €</td></tr>
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
                    <tr>
                        <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                        <td class="right">{{ \App\Support\Formats::duration((int) $row['minutes'], 'clock', withUnit: false) }}</td>
                        <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
