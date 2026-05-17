@extends('layouts.app')
@section('title', __('Fuhrpark-Auswertung'))
@section('nav-title', __('Fuhrpark-Auswertung'))

@section('content')
@php
    $money = fn (float $v) => number_format($v, 2, ',', '.') . ' €';
    $km    = fn (float $v) => number_format($v, 1, ',', '.') . ' km';
    $num   = fn (float $v, int $d = 2) => number_format($v, $d, ',', '.');
@endphp

<div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-auto">

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
            <div class="rounded-box border border-base-300 bg-base-200 p-6 text-center text-sm text-base-content/60">
                {{ __('Keine Fahrzeug-Daten im gewählten Zeitraum.') }}
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-zebra table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Fahrzeug') }}</th>
                            <th>{{ __('Antrieb') }}</th>
                            <th class="text-right">{{ __('Fahrten') }}</th>
                            <th class="text-right">{{ __('km') }}</th>
                            <th class="text-right">{{ __('Erstattung') }}</th>
                            <th class="text-right">{{ __('Tankungen') }}</th>
                            <th class="text-right">{{ __('Liter') }}</th>
                            <th class="text-right">{{ __('kWh') }}</th>
                            <th class="text-right">{{ __('Energiekosten') }}</th>
                            <th class="text-right">{{ __('€/km') }}</th>
                            <th class="text-right">{{ __('Tachostand') }}</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                <td class="text-right tabular-nums">{{ $num($r['km'], 1) }}</td>
                                <td class="text-right tabular-nums">{{ $money($r['reimbursement']) }}</td>
                                <td class="text-right tabular-nums">{{ $r['fuel_count'] }}</td>
                                <td class="text-right tabular-nums">{{ $r['liters'] > 0 ? $num($r['liters'], 2) : '–' }}</td>
                                <td class="text-right tabular-nums">{{ $r['kwh'] > 0 ? $num($r['kwh'], 2) : '–' }}</td>
                                <td class="text-right tabular-nums">{{ $money($r['energy_cost']) }}</td>
                                <td class="text-right tabular-nums">{{ $r['cost_per_km'] !== null ? $num($r['cost_per_km'], 3) . ' €' : '–' }}</td>
                                <td class="text-right tabular-nums">{{ $r['last_odometer'] !== null ? number_format($r['last_odometer'], 0, ',', '.') : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
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
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
