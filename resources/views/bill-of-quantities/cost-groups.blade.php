{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : cost-groups.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Kostengruppen: :name', ['name' => $bill->name]))
@section('nav-title', __('Kostengruppen'))

@php
    /** @var array{catalog: ?object, registry: ?object, rows: array, unassigned: float, total: float} $report */
    $money = static fn (float $value): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($value, 2, withThousandsSeparator: true) . ' €';
    $series = array_map(static fn (array $row): array => ['x' => $row['code'] . ' ' . $row['label'], 'y' => $row['amount']], $report['rows']);
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="text-sm text-base-content/70">
                {{ $bill->name }}
                @if ($report['catalog'])
                    · {{ $report['catalog']->name ?? $report['catalog']->catalog_key }}
                    @if ($report['registry'] && $report['registry']->edition)
                        ({{ $report['registry']->edition }})
                    @endif
                @endif
            </div>
            <x-slot:actions>
                {{-- Kostenanschlag = was vergeben wurde, Kostenfeststellung = was aufgemessen ist. --}}
                <x-icon-btn icon="request_quote" size="sm" show-label
                            :href="route('bill-of-quantities.cost-estimate.export', [$bill, 'stage' => 'quote'])"
                            :title="__('Kostenanschlag als GAEB X51')">{{ __('Kostenanschlag') }}</x-icon-btn>
                <x-icon-btn icon="fact_check" size="sm" show-label
                            :href="route('bill-of-quantities.cost-estimate.export', [$bill, 'stage' => 'final'])"
                            :title="__('Kostenfeststellung als GAEB X51')">{{ __('Kostenfeststellung') }}</x-icon-btn>
                <x-icon-btn icon="download" size="sm" :href="route('bill-of-quantities.cost-groups', [$bill, 'level' => $level, 'export' => 'csv'])" show-label>{{ __('CSV') }}</x-icon-btn>
                <x-icon-btn icon="table_view" size="sm" :href="route('bill-of-quantities.cost-groups', [$bill, 'level' => $level, 'export' => 'xlsx'])" show-label>Excel</x-icon-btn>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('bill-of-quantities.show', $bill)" show-label>{{ __('Zum Leistungsverzeichnis') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('bill-of-quantities.cost-groups', $bill)" :reset="route('bill-of-quantities.cost-groups', $bill)">
        <x-filter-field :label="__('Gliederungstiefe')" for="kg-level" class="shrink-0">
            <select id="kg-level" name="level" class="select select-sm select-bordered w-52" aria-label="{{ __('Gliederungstiefe') }}">
                <option value="1" @selected($level === 1)>{{ __('1. Ebene (300)') }}</option>
                <option value="2" @selected($level === 2)>{{ __('2. Ebene (310)') }}</option>
                <option value="3" @selected($level === 3)>{{ __('3. Ebene (311)') }}</option>
            </select>
        </x-filter-field>
    </x-filter-bar>

    @if ($report['catalog'] === null)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">category</span>'
                       :title="__('Kein Kostengruppenkatalog im Leistungsverzeichnis.')"
                       :message="__('Kostengruppen kommen mit der Datei der Vergabestelle. Ohne Katalog im Kopf gibt es nichts zuzuordnen — die Positionen erscheinen dann vollständig unter „ohne Zuordnung“.')" />
    @elseif (empty($report['rows']) && $report['unassigned'] <= 0.0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">category</span>'
                       :title="__('Keine bepreisten Positionen.')"
                       :message="__('Die Auswertung rechnet Menge × Einheitspreis; ohne Preise gibt es nichts zu summieren.')" />
    @else
        @if (! empty($series))
            <x-charts.bar-h :title="__('Summe je Kostengruppe')" :unit="__('EUR')" :series="$series"
                            :x-label="__('Kostengruppe')" :y-label="__('EUR')"
                            :note="__('Menge × Einheitspreis; Teilmengen anteilig.')" />
        @endif

        <x-card>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kostengruppe') }}</th>
                        <th class="text-right">{{ __('Summe') }}</th>
                        <th class="text-right">{{ __('Anteil') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($report['rows'] as $row)
                    <tr>
                        {{-- Drill-down: Wer eine Summe liest, will die Positionen
                             dahinter sehen. --}}
                        <td>
                            <a class="link" href="{{ route('bill-of-quantities.catalog-assignment', [$bill, 'code' => $row['code']]) }}">
                                <span class="font-mono">{{ $row['code'] }}</span> {{ $row['label'] }}
                            </a>
                        </td>
                        <td class="text-right tabular-nums">{{ $money($row['amount']) }}</td>
                        <td class="text-right tabular-nums text-base-content/70">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['share'], 1) }} %</td>
                    </tr>
                @endforeach

                {{-- Der Rest ohne Zuordnung steht immer da — auch bei 0,00 €.
                     Eine Auswertung, die ihn verschweigt, ist nicht prüfbar. --}}
                <tr class="border-t-2 border-base-300">
                    <td class="text-base-content/70">{{ __('Ohne Zuordnung') }}</td>
                    <td class="text-right tabular-nums @if ($report['unassigned'] > 0.0) text-warning font-medium @endif">{{ $money($report['unassigned']) }}</td>
                    <td class="text-right tabular-nums text-base-content/70">
                        {{ $report['total'] > 0.0 ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($report['unassigned'] / $report['total'] * 100, 1) : '0,0' }} %
                    </td>
                </tr>
                <tr class="font-medium">
                    <td>{{ __('Gesamt') }}</td>
                    <td class="text-right tabular-nums">{{ $money($report['total']) }}</td>
                    <td class="text-right tabular-nums">100,0 %</td>
                </tr>
            </x-table>
        </x-card>

        {{-- Kostenverfolgung (MVP-643): Was war ausgeschrieben, was kam als
             Nachtrag hinzu, was ist aufgemessen — Abweichungen bleiben stehen. --}}
        <x-card :title="__('Kostenverfolgung')">
            <p class="mb-3 text-sm text-base-content/70">
                @if ($lifecycle['estimate'])
                    {{ __('Budget aus :name (:stage, Stand :date). Der abgerechnete Stand fehlt bewusst — er liegt im führenden Faktura-System.', [
                        'name' => $lifecycle['estimate']->name,
                        'stage' => $lifecycle['estimate']->stageLabel(),
                        'date' => $lifecycle['estimate']->determined_on->format('d.m.Y'),
                    ]) }}
                @else
                    {{ __('Kein Budget hinterlegt: Es stammt aus einer Kostenermittlung am Projekt (X51-Import). Der abgerechnete Stand liegt im führenden Faktura-System.') }}
                @endif
            </p>
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kostengruppe') }}</th>
                        @if ($lifecycle['estimate'])<th class="text-right">{{ __('Budget') }}</th>@endif
                        <th class="text-right">{{ __('LV-Ansatz') }}</th>
                        <th class="text-right">{{ __('Nachträge') }}</th>
                        <th class="text-right">{{ __('Aufgemessen') }}</th>
                        <th class="text-right">{{ __('Rest') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($lifecycle['rows'] as $row)
                    <tr>
                        <td>
                            @if ($row['code'] === '')
                                <span class="text-base-content/70">{{ $row['label'] }}</span>
                            @else
                                <span class="font-mono">{{ $row['code'] }}</span> {{ $row['label'] }}
                            @endif
                        </td>
                        @if ($lifecycle['estimate'])
                            {{-- Eine Kostengruppe ohne Budgetzeile ist nicht „0 €",
                                 sondern in der Ermittlung nicht vorgesehen. --}}
                            <td class="text-right tabular-nums text-base-content/70">{{ $row['budget'] > 0.0 ? $money($row['budget']) : '—' }}</td>
                        @endif
                        <td class="text-right tabular-nums">{{ $money($row['boq']) }}</td>
                        <td class="text-right tabular-nums">{{ $row['addenda'] > 0.0 ? $money($row['addenda']) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $money($row['executed']) }}</td>
                        {{-- Ein Aufmaß über der LV-Menge ergibt einen negativen
                             Rest — genau das gehört gezeigt, nicht geglättet. --}}
                        <td class="text-right tabular-nums @if ($row['remaining'] < 0.0) text-error font-medium @endif">{{ $money($row['remaining']) }}</td>
                    </tr>
                @endforeach
                <tr class="border-t-2 border-base-300 font-medium">
                    <td>{{ __('Gesamt') }}</td>
                    @if ($lifecycle['estimate'])
                        <td class="text-right tabular-nums">{{ $money($lifecycle['totals']['budget'] ?? 0.0) }}</td>
                    @endif
                    <td class="text-right tabular-nums">{{ $money($lifecycle['totals']['boq']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($lifecycle['totals']['addenda']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($lifecycle['totals']['executed']) }}</td>
                    <td class="text-right tabular-nums">{{ $money($lifecycle['totals']['remaining']) }}</td>
                </tr>
            </x-table>
        </x-card>
    @endif
</x-page-shell>
@endsection
