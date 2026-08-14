{{--
  Created on   : Tue May 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : assets.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Produktanalyse')
@section('pdf-heading', 'Produktanalyse')

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>{{ match($groupBy) { 'group' => 'Produktgruppe', 'model' => 'Modell', default => 'Asset' } }}</th>
                <th class="num">Assets</th>
                <th class="num">Auftraege</th>
                <th class="num">{{ __('Offene Punkte') }}</th>
                <th class="num">Eskaliert</th>
                <th class="num">Defekte</th>
                <th class="num">Defektrate %</th>
                <th class="num">{{ __('Wartungssitzungen') }}</th>
                <th class="num">{{ __('Wartungszeit') }} (min)</th>
                <th>{{ __('Letzter Vorfall') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="num">{{ $row['assetCount'] }}</td>
                    <td class="num">{{ $row['entryCount'] }}</td>
                    <td class="num">{{ $row['openIssueCount'] }}</td>
                    <td class="num">{{ $row['escalationCount'] }}</td>
                    <td class="num">{{ $row['defectCount'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['defectRate'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['maintenanceSessions'] }}</td>
                    <td class="num">{{ $row['maintenanceMinutes'] }}</td>
                    <td>{{ $row['lastIncidentAt'] ? \Illuminate\Support\Carbon::parse($row['lastIncidentAt'])->fdate() : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
