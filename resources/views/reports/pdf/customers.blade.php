@extends('reports.pdf.layout')

@section('pdf-title', 'Kundenanalyse')
@section('pdf-heading', 'Kundenanalyse')

@section('pdf-table')
    <table>
        <thead>
            <tr>
                <th>Kunde</th>
                <th class="num">Auftraege</th>
                <th class="num">Gesamt</th>
                <th class="num">Abrechenbar</th>
                <th class="num">{{ __('Nicht abrechenbar') }}</th>
                <th class="num">Anteil %</th>
                <th class="num">Nacharbeit</th>
                <th class="num">{{ __('Offene Punkte') }}</th>
                <th class="num">Eskaliert</th>
                <th class="num">Durchschnitt</th>
                <th class="num">Trend 30d</th>
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
                    <td class="num">{{ number_format((float) $row['nonBillableShare'], 2, ',', '.') }}</td>
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
