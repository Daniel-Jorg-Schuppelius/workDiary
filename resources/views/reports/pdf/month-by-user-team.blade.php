@extends('reports.pdf.layout')

@section('pdf-title', 'Team-Monatsreport – ' . $year)
@section('pdf-heading', 'Team-Monatsreport – ' . $year)

@section('pdf-meta')
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
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
                @php
                    $hT = intdiv((int) $row['total'], 60);
                    $mT = (int) $row['total'] % 60;
                @endphp
                <tr>
                    <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                    @foreach ($row['months'] as $minutes)
                        @php
                            $h = intdiv((int) $minutes, 60);
                            $m = (int) $minutes % 60;
                        @endphp
                        <td class="right">{{ $minutes > 0 ? sprintf('%d:%02d', $h, $m) : '–' }}</td>
                    @endforeach
                    <td class="right">{{ $hT }}:{{ str_pad((string) $mT, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="right">{{ number_format((float) $row['rate'], 2, ',', '.') }} €</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            @php
                $hY = intdiv((int) $yearTotal, 60);
                $mY = (int) $yearTotal % 60;
            @endphp
            <tr>
                <td>Σ Monat</td>
                @foreach ($monthTotals as $m)
                    @php
                        $h = intdiv((int) $m, 60);
                        $mm = (int) $m % 60;
                    @endphp
                    <td class="right">{{ $m > 0 ? sprintf('%d:%02d', $h, $mm) : '–' }}</td>
                @endforeach
                <td class="right">{{ $hY }}:{{ str_pad((string) $mY, 2, '0', STR_PAD_LEFT) }}</td>
                <td class="right">{{ number_format((float) $yearRate, 2, ',', '.') }} €</td>
            </tr>
        </tfoot>
    </table>
@endsection
