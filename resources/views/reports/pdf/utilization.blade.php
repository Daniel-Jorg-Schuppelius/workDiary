{{--
  Created on   : Sun Aug 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : utilization.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', 'Auslastung und Realisierung')
@section('pdf-heading', 'Auslastung & Realisierung')

@section('pdf-table')
    <p class="small">
        {{ __('Zeitraum') }}: {{ $label }} ·
        {{ __('Auslastung gesamt') }}: {{ $totals['utilization'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['utilization'], 1) . ' %' : '–' }} ·
        {{ __('Abrechenbare Quote') }}: {{ $totals['billableRate'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['billableRate'], 1) . ' %' : '–' }}
    </p>

    @include('reports.pdf.charts._chart')

    <table>
        <thead>
            <tr>
                <th>{{ __('Person') }}</th>
                <th class="num">{{ __('Soll (Min.)') }}</th>
                <th class="num">{{ __('Erfasst (Min.)') }}</th>
                <th class="num">{{ __('Abrechenbar (Min.)') }}</th>
                @if ($hasInvoiceData)<th class="num">{{ __('Fakturiert (Min.)') }}</th>@endif
                <th class="num">{{ __('Auslastung %') }}</th>
                <th class="num">{{ __('Abrechenbare Quote %') }}</th>
                @if ($hasInvoiceData)<th class="num">{{ __('Realisierung %') }}</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['userName'] }}</td>
                    <td class="num">{{ $row['targetMinutes'] }}</td>
                    <td class="num">{{ $row['trackedMinutes'] }}</td>
                    <td class="num">{{ $row['billableMinutes'] }}</td>
                    @if ($hasInvoiceData)<td class="num">{{ $row['invoicedMinutes'] }}</td>@endif
                    <td class="num">{{ $row['utilization'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['utilization'], 1) : '–' }}</td>
                    <td class="num">{{ $row['billableRate'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['billableRate'], 1) : '–' }}</td>
                    @if ($hasInvoiceData)<td class="num">{{ $row['realization'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['realization'], 1) : '–' }}</td>@endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th>{{ __('Gesamt') }}</th>
                <th class="num">{{ $totals['targetMinutes'] }}</th>
                <th class="num">{{ $totals['trackedMinutes'] }}</th>
                <th class="num">{{ $totals['billableMinutes'] }}</th>
                @if ($hasInvoiceData)<th class="num">{{ $totals['invoicedMinutes'] }}</th>@endif
                <th class="num">{{ $totals['utilization'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['utilization'], 1) : '–' }}</th>
                <th class="num">{{ $totals['billableRate'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['billableRate'], 1) : '–' }}</th>
                @if ($hasInvoiceData)<th class="num">{{ $totals['realization'] !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['realization'], 1) : '–' }}</th>@endif
            </tr>
        </tfoot>
    </table>
@endsection
