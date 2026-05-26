@extends('layouts.app')
@section('title', __('Fuhrpark-Auswertung'))
@section('nav-title', __('Fuhrpark-Auswertung'))

@section('content')
@php
    $money = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
    $km    = fn (float $v) => number_format($v, 1, ',', '.') . ' km';
    $num   = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
@endphp

<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Fahrten, Tankungen, Energiekosten und Erstattungen je Fahrzeug.')">
            <x-slot:actions>
                <x-icon-btn icon="download" tone="outline" size="sm"
                            :href="route('reports.fleet', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv']))"
                            show-label>CSV</x-icon-btn>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm"
                            :href="route('reports.fleet', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf']))"
                            show-label>PDF</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($isAdmin)
        <x-filter-bar :action="route('reports.fleet')" :reset="route('reports.fleet')">
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine Fahrten') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamter Fuhrpark') }}</option>
                </select>
            </x-filter-field>
        </x-filter-bar>
    @endif

    {{-- KPI-Kacheln --}}
    <div class="grid gap-3 grid-cols-1 sm:grid-flow-col sm:auto-cols-fr">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Fahrzeuge') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['vehicles'] }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Σ km') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $km($totals['km']) }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ $totals['trip_count'] }} {{ __('Fahrten') }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Tankungen / Ladungen') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $totals['fuel_count'] }}</div>
            <div class="mt-1 text-xs text-base-content/60">
                @if ($totals['liters'] > 0){{ $num($totals['liters'], 1) }} L @endif
                @if ($totals['kwh'] > 0)· {{ $num($totals['kwh'], 1) }} kWh @endif
            </div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Energiekosten') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">{{ $money($totals['energy_cost']) }}</div>
            <div class="mt-1 text-xs text-base-content/60">{{ __('Erstattung') }} {{ $money($totals['reimbursement']) }}</div>
        </div>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="text-xs uppercase tracking-wider text-base-content/60">{{ __('Ø €/km') }}</div>
            <div class="mt-1 font-['Space_Grotesk'] text-3xl font-bold">
                {{ $totals['avg_cost_per_km'] !== null ? $num($totals['avg_cost_per_km'], 3) . ' €' : '–' }}
            </div>
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        @if (empty($rows))
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">directions_car</span>' :title="__('Keine Fahrzeug-Daten im gewählten Zeitraum.')" />
        @else
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Fahrzeug') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Antrieb') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Fahrten') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('km') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Erstattung') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Tankungen') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Liter') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('kWh') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Energiekosten') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('€/km') }}</x-table.th>
                        <x-table.th sort type="number" align="right">{{ __('Tachostand') }}</x-table.th>
                    </tr>
                </x-slot:head>
                <x-slot:foot>
                    <tr class="font-bold">
                        <td colspan="2">{{ __('Gesamt') }}</td>
                        <td class="text-right tabular-nums">{{ $totals['trip_count'] }}</td>
                        <td class="text-right tabular-nums">{{ $num($totals['km'], 1) }}</td>
                        <td class="text-right tabular-nums">{{ $money($totals['reimbursement']) }}</td>
                        <td class="text-right tabular-nums">{{ $totals['fuel_count'] }}</td>
                        <td class="text-right tabular-nums">{{ $num($totals['liters'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ $num($totals['kwh'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ $money($totals['energy_cost']) }}</td>
                        <td class="text-right tabular-nums">{{ $totals['avg_cost_per_km'] !== null ? $num($totals['avg_cost_per_km'], 3) . ' €' : '–' }}</td>
                        <td></td>
                    </tr>
                </x-slot:foot>
                @foreach ($rows as $r)
                    <tr>
                        <td>
                            <span class="font-semibold">{{ $r['vehicle']->license_plate }}</span>
                            @if ($r['vehicle']->label)
                                <span class="ml-1 text-xs text-base-content/60">{{ $r['vehicle']->label }}</span>
                            @endif
                        </td>
                        <td class="text-xs text-base-content/70">{{ $r['vehicle']->propulsion->label() }}</td>
                        <td class="text-right tabular-nums">{{ $r['trip_count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['km'] }}">{{ $num($r['km'], 1) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['reimbursement'] }}">{{ $money($r['reimbursement']) }}</td>
                        <td class="text-right tabular-nums">{{ $r['fuel_count'] }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['liters'] }}">{{ $r['liters'] > 0 ? $num($r['liters'], 2) : '–' }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['kwh'] }}">{{ $r['kwh'] > 0 ? $num($r['kwh'], 2) : '–' }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ (float) $r['energy_cost'] }}">{{ $money($r['energy_cost']) }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $r['cost_per_km'] ?? -1 }}">{{ $r['cost_per_km'] !== null ? $num($r['cost_per_km'], 3) . ' €' : '–' }}</td>
                        <td class="text-right tabular-nums" data-sort-value="{{ $r['last_odometer'] ?? -1 }}">{{ $r['last_odometer'] !== null ? number_format($r['last_odometer'], 0, ',', '.') : '–' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </div>
</x-page-shell>
@endsection
