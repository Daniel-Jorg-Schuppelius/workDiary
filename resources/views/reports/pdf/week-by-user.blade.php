{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : week-by-user.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Woche – ' . $weekLabel)
@section('pdf-heading', 'Woche pro Mitarbeiter – ' . $weekLabel)

@section('pdf-meta')
    Erstellt: {{ now()->fdatetime() }}
@endsection

@section('pdf-table')
    @include('reports.pdf.charts._chart')

    <table class="data">
        <thead>
            <tr>
                <th>Mitarbeiter</th>
                @foreach ($dayLabels as $label)
                    <th class="right">{{ $label }}</th>
                @endforeach
                <th class="right">Σ Stunden</th>
                <th class="right">{{ __('Erlös') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($byUser as $uid => $row)
                <tr>
                    <td>{{ $users->get($uid)?->name ?? '#' . $uid }}</td>
                    @foreach ($row['days'] as $minutes)
                        <td class="right">{{ $minutes > 0 ? \App\Support\Formats::duration((int) $minutes, 'clock', withUnit: false) : '–' }}</td>
                    @endforeach
                    <td class="right">{{ \App\Support\Formats::duration((int) $row['total'], 'clock', withUnit: false) }}</td>
                    <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $row['rate'], 2, withThousandsSeparator: true) }} €</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Σ Tag</td>
                @foreach ($dayTotals as $m)
                    <td class="right">{{ $m > 0 ? \App\Support\Formats::duration((int) $m, 'clock', withUnit: false) : '–' }}</td>
                @endforeach
                <td class="right">{{ \App\Support\Formats::duration((int) $weekTotal, 'clock', withUnit: false) }}</td>
                <td class="right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $weekRate, 2, withThousandsSeparator: true) }} €</td>
            </tr>
        </tfoot>
    </table>
@endsection
