{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : customer-project.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')
@section('title', __('Kunden & Projekte'))
@section('nav-title', __('Kunden & Projekte'))

@section('content')
@php
    $fmt = fn (int $min): string => \App\Support\Formats::duration($min, 'clock');
    $money = function (float $val): string {
        return \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($val, 2, withThousandsSeparator: true) . ' €';
    };
    $linkParams = array_filter(array_merge(
        ['scope' => $seesAll ? $scope : null, 'foreign_customer' => $foreignCustomerParam !== '' ? $foreignCustomerParam : null],
        $standardFilters->toQueryParams(),
    ));
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Aggregierte Stunden und Erlöse pro Kunde und Projekt im gewählten Zeitraum.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.customer-project', array_merge($linkParams, ['export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="table_chart" tone="outline" size="sm"
                            :href="route('reports.customer-project', array_merge($linkParams, ['export' => 'xlsx']))"
                            show-label>XLSX</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.customer-project', array_merge($linkParams, ['export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-filter-bar :action="route('reports.customer-project')" :reset="route('reports.customer-project')">
        @if ($seesAll)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" data-autosubmit>
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamtes Team') }}</option>
                </select>
            </x-filter-field>
        @endif
        @include('reports._standard_filters', ['idPrefix' => 'customer-project'])
        @if ($foreignCustomerParam !== '')
            {{-- Endkunden-Einschränkung (Link-Parameter) beim Umfiltern beibehalten. --}}
            <input type="hidden" name="foreign_customer" value="{{ $foreignCustomerParam }}">
        @endif
    </x-filter-bar>

    <div class="chart-grid grid gap-3 xl:grid-cols-2">
        <x-charts.pareto :title="__('Stunden je Kunde (Top 20)')" unit="h" :series="$customerHoursSeries" :x-label="__('Kunde')" :y-label="__('Stunden')" />
        <x-charts.bar-h :title="__('Top-Projekte nach Stunden')" unit="h" :series="$topProjectsSeries" :x-label="__('Projekt')" :y-label="__('Stunden')" />
    </div>

    <x-card>
        <div class="mb-3 flex flex-wrap items-baseline justify-end gap-2">
            <div class="flex items-baseline gap-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ Std.</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $totalMinutes > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $fmt($totalMinutes) }}
                    </span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-xs uppercase tracking-[0.18em] text-base-content/60">Σ €</span>
                    <span class="font-['Space_Grotesk'] text-xl font-semibold {{ $totalRate > 0 ? 'text-primary' : 'text-base-content/50' }}">
                        {{ $money($totalRate) }}
                    </span>
                </div>
            </div>
        </div>

        @if (empty($bucket))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">business_center</span>' :title="__('Keine Zeiteinträge im gewählten Zeitraum.')" />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Kunde / Projekt') }}</th>
                        <th class="text-right">{{ __('Stunden') }}</th>
                        <th class="text-right">{{ __('Erlös') }}</th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td>{{ __('Gesamt') }}</td>
                        <td class="text-right">{{ $fmt($totalMinutes) }}</td>
                        <td class="text-right">{{ $money($totalRate) }}</td>
                    </tr>
                </x-slot:foot>
                @foreach ($bucket as $row)
                    <tr class="bg-base-200/60">
                        <th class="font-semibold text-base-content">
                            {{ $row['customer']?->name ?? __('Ohne Kunde') }}
                        </th>
                        <th class="text-right font-semibold tabular-nums text-base-content">{{ $fmt($row['minutes']) }}</th>
                        <th class="text-right font-semibold tabular-nums text-base-content">{{ $money($row['rate']) }}</th>
                    </tr>
                    @foreach ($row['projects'] as $entry)
                        <tr>
                            <td class="pl-8 text-sm text-base-content/80">
                                @if ($entry['project']->color)
                                    <span class="mr-2 inline-block size-2 rounded-full align-middle" style="background-color: {{ $entry['project']->color }};"></span>
                                @endif
                                {{ $entry['project']->name }}
                                @if ($entry['project']->number)
                                    <span class="ml-1 text-xs text-base-content/50">#{{ $entry['project']->number }}</span>
                                @endif
                                @if ($entry['project']->foreignCustomer)
                                    <span class="ml-1 text-xs text-base-content/50">· {{ $entry['project']->foreignCustomer->name }}</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ $fmt($entry['minutes']) }}</td>
                            <td class="text-right tabular-nums">{{ $money($entry['rate']) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </x-table>
        @endif
    </x-card>
</x-page-shell>
@endsection
