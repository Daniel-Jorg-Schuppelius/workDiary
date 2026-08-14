{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : economics.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('reports.pdf.layout')

@section('pdf-title', __('Wirtschaftlichkeit'))
@section('pdf-heading', __('Wirtschaftlichkeit'))

@php
    $eur = fn($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true) . ' €';
    $pct = fn($v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v instanceof \CommonToolkit\ValueObjects\Money ? $v->toFloat() : (float) $v, 2, withThousandsSeparator: true) . ' %';
@endphp

@section('pdf-table')
    @include('reports.pdf.charts._chart')

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

    {{-- MVP-332: LV-Dimension (nur mit Projektfilter und vorhandenem LV). --}}
    @if(($byBoq ?? null) !== null && $byBoq['hasBoq'])
        <h2 style="font-size:13px;margin:12px 0 4px;">{{ __('Nachkalkulation je LV-Position') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Ordnungszahl') }}</th>
                    <th>{{ __('Position') }}</th>
                    <th>{{ __('Nachtrag') }}</th>
                    <th class="num">{{ __('Aufmaß (Menge)') }}</th>
                    <th>{{ __('Einheit') }}</th>
                    <th class="num">{{ __('Erlös (Aufmaß × EP)') }}</th>
                    <th class="num">{{ __('Zeit (Min.)') }}</th>
                    <th class="num">{{ __('Kosten Zeit') }}</th>
                    <th class="num">{{ __('Kosten Material') }}</th>
                    <th class="num">{{ __('Kosten') }}</th>
                    <th class="num">{{ __('Deckungsbeitrag') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byBoq['positions'] as $p)
                    <tr>
                        <td>{{ $p['referenceNo'] }}</td>
                        <td>{{ $p['shortText'] ?? '—' }}</td>
                        <td>{{ $p['isAddendum'] ? __('Ja') : __('Nein') }}</td>
                        <td class="num">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($p['measuredQuantity'], 3, withThousandsSeparator: true) }}</td>
                        <td>{{ $p['unit'] ?? '—' }}</td>
                        <td class="num">{{ $eur($p['revenue']) }}</td>
                        <td class="num">{{ $p['timeMinutes'] }}</td>
                        <td class="num">{{ $eur($p['costTime']) }}</td>
                        <td class="num">{{ $eur($p['costMaterial']) }}</td>
                        <td class="num">{{ $eur($p['cost']) }}</td>
                        <td class="num">{{ $eur($p['contribution']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11">{{ __('Keine Daten') }}</td></tr>
                @endforelse
                @if($byBoq['unassigned']['cost'] > 0 || $byBoq['unassigned']['timeMinutes'] > 0)
                    <tr>
                        <td>{{ __('Ohne LV-Zuordnung') }}</td>
                        <td>{{ __('Quellposten ohne Positions-Verknüpfung (inkl. aller Spesen)') }}</td>
                        <td>—</td>
                        <td class="num">—</td>
                        <td>—</td>
                        <td class="num">—</td>
                        <td class="num">{{ $byBoq['unassigned']['timeMinutes'] }}</td>
                        <td class="num">{{ $eur($byBoq['unassigned']['costTime']) }}</td>
                        <td class="num">{{ $eur($byBoq['unassigned']['costMaterial']) }}</td>
                        <td class="num">{{ $eur($byBoq['unassigned']['cost']) }}</td>
                        <td class="num">—</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif
@endsection
