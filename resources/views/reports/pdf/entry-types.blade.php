@extends('reports.pdf.layout')

@section('pdf-title', 'Auftragstypanalyse')
@section('pdf-heading', 'Auftragstypanalyse')

@section('pdf-table')
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
                    <td class="num">{{ number_format($row['avgPlannedMinutes'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['avgActualMinutes'], 2, ',', '.') }}</td>
                    <td class="num">{{ $row['planActualRatio'] === null ? '—' : number_format($row['planActualRatio'], 3, ',', '.') }}</td>
                    <td class="num">{{ $row['overrunCount'] }}</td>
                    <td class="num">{{ number_format($row['overrunShare'], 2, ',', '.') }}</td>
                    <td class="num">{{ $row['reworkCount'] }}</td>
                    <td class="num">{{ number_format($row['reworkShare'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['escalationShare'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['firstTimeRightShare'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['medianActualMinutes'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['p90ActualMinutes'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
