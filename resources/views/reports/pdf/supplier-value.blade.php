{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : supplier-value.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Lieferantenwert')
@section('pdf-heading', 'Lieferantenwert')

@section('pdf-table')
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        {{ __('Ausgaben gesamt') }}: {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['totalSpend'], 2, withThousandsSeparator: true) }} € ·
        {{ __('Top-5-Anteil') }}: {{ $concentration['top5Share'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($concentration['top5Share'], 1) . ' %' : '–' }} ·
        HHI: {{ $concentration['hhi'] ?? '–' }}
    </p>

    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>{{ __('Lieferant') }}</th>
                <th>{{ __('Segment') }}</th>
                <th class="num">{{ __('Tage seit Beleg') }}</th>
                <th class="num">{{ __('Belegtage') }}</th>
                <th class="num">{{ __('Ausgaben') }} (€)</th>
                <th class="num">{{ __('Anteil %') }}</th>
                <th class="num">R</th>
                <th class="num">F</th>
                <th class="num">M</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['supplierName'] }}</td>
                    <td>{{ $segmentLabels[$row['segment']] ?? $row['segment'] }}</td>
                    <td class="num">{{ $row['recencyDays'] ?? '–' }}</td>
                    <td class="num">{{ $row['frequencyDays'] }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['spend'], 2, withThousandsSeparator: true) }}</td>
                    <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['spendShare'], 1) }}</td>
                    <td class="num">{{ $row['r'] ?? '–' }}</td>
                    <td class="num">{{ $row['f'] ?? '–' }}</td>
                    <td class="num">{{ $row['m'] ?? '–' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
