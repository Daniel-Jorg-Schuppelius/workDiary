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

    <x-filter-bar :action="route('reports.fleet')" :reset="route('reports.fleet')">
        @if ($isAdmin)
            <x-filter-field :label="__('Bereich')" for="rep-scope">
                <select id="rep-scope" name="scope" class="select select-sm select-bordered" onchange="this.form.submit()">
                    <option value="mine" @selected($scope === 'mine')>{{ __('Nur meine Fahrten') }}</option>
                    <option value="team" @selected($scope === 'team')>{{ __('Gesamter Fuhrpark') }}</option>
                </select>
            </x-filter-field>
        @endif
        <x-slot:extra>
            <a href="{{ route('reports.fleet', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'csv'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="download" />CSV
            </a>
            <a href="{{ route('reports.fleet', array_filter(['scope' => $isAdmin ? $scope : null, 'export' => 'pdf'])) }}" class="btn btn-sm btn-outline gap-1">
                <x-icon name="picture_as_pdf" />PDF
            </a>
        </x-slot:extra>
    </x-filter-bar>

    {{-- KPI-Kacheln --}}
    <div class="stats stats-vertical sm:stats-horizontal w-full rounded-box border border-base-300 bg-base-100 shadow-xs">
        <div class="stat">
            <div class="stat-title">{{ __('Fahrzeuge') }}</div>
            <div class="stat-value text-2xl">{{ $totals['vehicles'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Σ km') }}</div>
            <div class="stat-value text-2xl">{{ $km($totals['km']) }}</div>
            <div class="stat-desc">{{ $totals['trip_count'] }} {{ __('Fahrten') }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Tankungen / Ladungen') }}</div>
            <div class="stat-value text-2xl">{{ $totals['fuel_count'] }}</div>
            <div class="stat-desc">
                @if ($totals['liters'] > 0){{ $num($totals['liters'], 1) }} L @endif
                @if ($totals['kwh'] > 0)· {{ $num($totals['kwh'], 1) }} kWh @endif
            </div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Energiekosten') }}</div>
            <div class="stat-value text-2xl">{{ $money($totals['energy_cost']) }}</div>
            <div class="stat-desc">{{ __('Erstattung') }} {{ $money($totals['reimbursement']) }}</div>
        </div>
        <div class="stat">
            <div class="stat-title">{{ __('Ø €/km') }}</div>
            <div class="stat-value text-2xl">
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
                        <td class="text-xs text-base-content/70">{{ __($r['vehicle']->propulsion) }}</td>
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
