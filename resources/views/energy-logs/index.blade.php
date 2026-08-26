{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Tank- & Ladelog'))
@section('nav-title', __('Tank- & Ladelog'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Tank- und Ladevorgänge der Fahrzeuge erfassen.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('energy-logs.create')"
                        show-label>{{ __('Neuer Eintrag') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('energy-logs.index')" :reset="route('energy-logs.index')">
            @if ($selectableUsers)
                <x-filter-field :label="__('Nutzer')" for="energy-user">
                    <select id="energy-user" name="user" class="select select-bordered select-sm" data-autosubmit>
                        <option value="">{{ __('— eigene —') }}</option>
                        <option value="all" @selected(request('user') === 'all')>{{ __('Alle') }}</option>
                        @foreach ($selectableUsers as $u)
                            <option value="{{ $u->sqid }}" @selected(request('user') === $u->sqid)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
            <x-filter-field :label="__('Fahrzeug')" for="energy-vehicle">
                <select id="energy-vehicle" name="vehicle" class="select select-bordered select-sm" data-autosubmit>
                    <option value="">{{ __('Alle Fahrzeuge') }}</option>
                    @foreach ($vehicles as $v)
                        <option value="{{ $v->sqid }}" @selected((string) ($selectedVehicleSqid ?? '') === $v->sqid)>{{ $v->displayName() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @foreach (request()->except(['user', 'vehicle']) as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
        </x-filter-bar>

        <div class="grid gap-3 sm:grid-cols-4">
            <x-kpi-tile :label="__('Liter gesamt')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['liters'], 2, withThousandsSeparator: true) . ' l'" />
            <x-kpi-tile :label="__('kWh gesamt')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['kwh'], 2, withThousandsSeparator: true) . ' kWh'" />
            <x-kpi-tile :label="__('Kosten')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['cost'], 2, withThousandsSeparator: true) . ' €'" />
            <x-kpi-tile :label="__('Strecke (Δ)')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['distance'], 0, withThousandsSeparator: true) . ' km'" />
        </div>

        <x-table :zebra="true" table-sort="server"
                 :route="route('energy-logs.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="array_filter([
                     'from' => $from->toDateString(),
                     'to' => $to->toDateString(),
                     'user' => request('user') === 'all'
                         ? 'all'
                         : (request()->filled('user') ? $targetUser?->sqid : null),
                     'vehicle' => $selectedVehicleSqid ?? null,
                 ], fn ($v) => $v !== null && $v !== '')"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="started_at" default>{{ __('Zeitpunkt') }}</x-table.th>
                    <th>{{ __('Fahrzeug') }}</th>
                    <th>{{ __('Nutzer') }}</th>
                    <x-table.th sort="type">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort="quantity" align="right">{{ __('Menge') }}</x-table.th>
                    <x-table.th sort="cost" align="right">{{ __('Kosten') }}</x-table.th>
                    <x-table.th sort="odometer" align="right">{{ __('Tacho') }}</x-table.th>
                    <x-table.th sort="distance" align="right">{{ __('Δ km') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($logs as $log)
                <tr>
                    <td>{{ $log->started_at?->fdatetime() }}</td>
                    <td>{{ $log->vehicle?->displayName() }}</td>
                    <td>{{ $log->user?->name }}</td>
                    <td>
                        <span class="badge badge-sm">{{ __($log->energy_type) }}</span>
                        @if ($log->fuel_kind)
                            <x-status-badge tone="ghost" size="sm">{{ __($log->fuel_kind) }}</x-status-badge>
                        @endif
                        @if ($log->charger_type)
                            <x-status-badge tone="ghost" size="sm">{{ __($log->charger_type) }}</x-status-badge>
                        @endif
                    </td>
                    <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $log->quantity, 2, withThousandsSeparator: true) }} {{ $log->unit === 'kwh' ? 'kWh' : 'l' }}</td>
                    <td class="text-right">{{ $log->cost_total !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $log->cost_total, 2, withThousandsSeparator: true) . ' €' : '—' }}</td>
                    <td class="text-right">{{ $log->odometer_km !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($log->odometer_km, 0, withThousandsSeparator: true) : '—' }}</td>
                    <td class="text-right">{{ $log->distance_since_last !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($log->distance_since_last, 0, withThousandsSeparator: true) : '—' }}</td>
                    <td class="text-right">
                        <x-icon-btn icon="edit"
                                    data-entry-modal-trigger
                                    :href="route('energy-logs.edit', $log)"
                                    :label="__('Bearbeiten')" />
                        <x-action-form :action="route('energy-logs.destroy', $log)" method="DELETE"
                              :confirm="__('Eintrag wirklich löschen?')"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </td>
                </tr>
            @empty
                <x-table.empty icon="bolt" :colspan="9" :title="__('Keine Einträge im gewählten Zeitraum')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$logs" standing />
    </x-index-page>
@endsection
