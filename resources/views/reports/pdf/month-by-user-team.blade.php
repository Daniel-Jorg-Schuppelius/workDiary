@extends('reports.pdf.layout')

@section('pdf-title', 'Team-Monatsreport – ' . $year)
@section('pdf-heading', 'Team-Monatsreport – ' . $year)

@section('pdf-meta')
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    <table class="data">
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                @foreach ($monthLabels as $label)
                    <th class="right">{{ $label }}</th>
                @endforeach
                <th class="right">Σ Std.</th>
                <th class="right">{{ __('Erlös') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($byUser as $uid => $row)
                <tr>
                    <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                    @foreach ($row['months'] as $minutes)
                        <td class="right">{{ $minutes > 0 ? \App\Support\Formats::duration((int) $minutes, 'clock', withUnit: false) : '–' }}</td>
                    @endforeach
                    <td class="right">{{ \App\Support\Formats::duration((int) $row['total'], 'clock', withUnit: false) }}</td>
                    <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Σ Monat</td>
                @foreach ($monthTotals as $m)
                    <td class="right">{{ $m > 0 ? \App\Support\Formats::duration((int) $m, 'clock', withUnit: false) : '–' }}</td>
                @endforeach
                <td class="right">{{ \App\Support\Formats::duration((int) $yearTotal, 'clock', withUnit: false) }}</td>
                <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $yearRate, 2, withThousandsSeparator: true) }} €</td>
            </tr>
        </tfoot>
    </table>
@endsection
