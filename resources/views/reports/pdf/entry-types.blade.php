{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : entry-types.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Auftragstypanalyse')
@section('pdf-heading', 'Auftragstypanalyse')

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>Auftragstyp</th>
                <th class="num">Auftraege</th>
                <th class="num">{{ __('Durchschnitt Plan') }}</th>
                <th class="num">{{ __('Durchschnitt Ist') }}</th>
                <th class="num">{{ __('Plan/Ist') }}</th>
                <th class="num">Ueberzug</th>
                <th class="num">Ueberzug %</th>
                <th class="num">Nacharbeit</th>
                <th class="num">Nacharbeit %</th>
                <th class="num">Escalation %</th>
                <th class="num">{{ __('First-Time-Right %') }}</th>
                <th class="num">{{ __('Median Ist') }}</th>
                <th class="num">{{ __('P90 Ist') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['entryTypeName'] }}</td>
                    <td class="num">{{ $row['entryCount'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['avgPlannedMinutes'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['avgActualMinutes'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['planActualRatio'] === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['planActualRatio'], 3, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['overrunCount'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['overrunShare'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['reworkCount'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['reworkShare'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['escalationShare'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['firstTimeRightShare'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['medianActualMinutes'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['p90ActualMinutes'], 2, withThousandsSeparator: true) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
