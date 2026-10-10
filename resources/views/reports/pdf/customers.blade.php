{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customers.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Kundenanalyse'))
@section('pdf-heading', __('Kundenanalyse'))

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>{{ __('Kunde') }}</th>
                <th class="num">{{ __('Aufträge') }}</th>
                <th class="num">{{ __('Gesamt') }}</th>
                <th class="num">{{ __('Abrechenbar') }}</th>
                <th class="num">{{ __('Nicht abrechenbar') }}</th>
                <th class="num">{{ __('Anteil %') }}</th>
                <th class="num">{{ __('Nacharbeit') }}</th>
                <th class="num">{{ __('Offene Punkte') }}</th>
                <th class="num">{{ __('Eskaliert') }}</th>
                <th class="num">{{ __('Ø Min./Auftrag') }}</th>
                <th class="num">{{ __('Trend 30d') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['customerName'] }}</td>
                    <td class="num">{{ $row['entryCount'] }}</td>
                    <td class="num">{{ $row['totalMinutes'] }}</td>
                    <td class="num">{{ $row['billableMinutes'] }}</td>
                    <td class="num">{{ $row['nonBillableMinutes'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['nonBillableShare'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ $row['reworkEntryCount'] }}</td>
                    <td class="num">{{ $row['openIssueCount'] }}</td>
                    <td class="num">{{ $row['escalationCount'] }}</td>
                    <td class="num">{{ $row['avgEntryMinutes'] }}</td>
                    <td class="num">{{ $row['trend30d'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
