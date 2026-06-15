@extends('reports.pdf.layout')

@section('pdf-title', __('Wirtschaftlichkeit'))
@section('pdf-heading', __('Wirtschaftlichkeit'))

@php
    $eur = fn($v): string => number_format((float) $v, 2, ',', '.') . ' €';
    $pct = fn($v): string => number_format((float) $v, 2, ',', '.') . ' %';
@endphp

@section('pdf-table')
    <h2 style="font-size:13px;margin:8px 0 4px;">{{ __('Wirtschaftlichkeit je Kunde') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Kunde') }}</th>
                <th class="num">{{ __('Abrechenbar (Min.)') }}</th>
                <th class="num">{{ __('Nicht abrechenbar (Min.)') }}</th>
                <th class="num">{{ __('Erlös') }}</th>
                <th class="num">{{ __('Kosten') }}</th>
                <th class="num">{{ __('Deckungsbeitrag') }}</th>
                <th class="num">{{ __('Marge') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byCustomer as $row)
                <tr>
                    <td>{{ $row['customerName'] }}</td>
                    <td class="num">{{ $row['billableMinutes'] }}</td>
                    <td class="num">{{ $row['nonBillableMinutes'] }}</td>
                    <td class="num">{{ $eur($row['revenue']) }}</td>
                    <td class="num">{{ $eur($row['cost']) }}</td>
                    <td class="num">{{ $eur($row['contribution']) }}</td>
                    <td class="num">{{ $pct($row['margin']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 style="font-size:13px;margin:12px 0 4px;">{{ __('Wirtschaftlichkeit & Plan-vs-Ist je Projekt') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Projekt') }}</th>
                <th>{{ __('Kunde') }}</th>
                <th class="num">{{ __('Erlös') }}</th>
                <th class="num">{{ __('Kosten') }}</th>
                <th class="num">{{ __('Deckungsbeitrag') }}</th>
                <th class="num">{{ __('Marge') }}</th>
                <th class="num">{{ __('Plan (Min.)') }}</th>
                <th class="num">{{ __('Ist (Min.)') }}</th>
                <th class="num">{{ __('Plan-Budget') }}</th>
                <th class="num">{{ __('Δ Budget') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byProject as $row)
                <tr>
                    <td>{{ $row['projectName'] }}</td>
                    <td>{{ $row['customerName'] }}</td>
                    <td class="num">{{ $eur($row['revenue']) }}</td>
                    <td class="num">{{ $eur($row['cost']) }}</td>
                    <td class="num">{{ $eur($row['contribution']) }}</td>
                    <td class="num">{{ $pct($row['margin']) }}</td>
                    <td class="num">{{ $row['planMinutes'] === null ? '–' : $row['planMinutes'] }}</td>
                    <td class="num">{{ $row['actualMinutes'] }}</td>
                    <td class="num">{{ $row['planBudget'] === null ? '–' : $eur($row['planBudget']) }}</td>
                    <td class="num">{{ $row['planBudgetDelta'] === null ? '–' : $eur($row['planBudgetDelta']) }}</td>
                </tr>
            @empty
                <tr><td colspan="10">{{ __('Keine Daten') }}</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
