{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Fahrtenbuch'))
@section('nav-title', __('Fahrtenbuch'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Dienstfahrten und Kilometerstände erfassen.')">
        <x-slot:actions>
            <x-icon-btn icon="download" size="sm"
                        :href="route('travel-logs.export', array_merge(request()->query(), ['from' => $from->toDateString(), 'to' => $to->toDateString()]))"
                        show-label>{{ __('CSV-Export') }}</x-icon-btn>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('travel-logs.create')"
                        show-label>{{ __('Neue Fahrt') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('travel-logs.index')" :reset="route('travel-logs.index')">
            <x-filter-field :label="__('Fahrzeug')" for="tl-vehicle">
                <select id="tl-vehicle" name="vehicle" class="select select-sm select-bordered shrink-0"
                        data-autosubmit>
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($vehicles as $v)
                        <option value="{{ $v->value }}" @selected($selectedVehicle === $v->value)>{{ $v->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-kpi-tile :label="__('Gefahrene Kilometer')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['distance_km'], 2, withThousandsSeparator: true) . ' km'" />
            <x-kpi-tile :label="__('Erstattung')" :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['reimbursement'], 2, withThousandsSeparator: true) . ' €'" />
        </div>

        <x-card padding="p-0" class="min-h-0 flex-1 flex flex-col overflow-hidden">
            <x-table table-sort="server"
                     :route="route('travel-logs.index')"
                     :current-sort="$sort ?? null"
                     :current-dir="$dir ?? 'desc'"
                     :sort-params="['from' => $from->toDateString(), 'to' => $to->toDateString()]"
                     bare scroll="flex" :pinRows="true">
                <x-slot:head>
                    <tr>
                        <x-table.th sort="date" default>{{ __('Datum') }}</x-table.th>
                        <x-table.th sort="from">{{ __('Von') }}</x-table.th>
                        <x-table.th sort="to">{{ __('Nach') }}</x-table.th>
                        <x-table.th sort="distance" align="right">{{ __('km') }}</x-table.th>
                        <x-table.th sort="vehicle">{{ __('Fahrzeug') }}</x-table.th>
                        <x-table.th sort="reimbursement" align="right">{{ __('Erstattung') }}</x-table.th>
                        <x-table.th sort="purpose">{{ __('Zweck') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->date?->fdate() }}</td>
                        <td>{{ $log->from_address }}</td>
                        <td>{{ $log->to_address }}</td>
                        <td class="text-right">
                            {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $log->distance_km, 2, withThousandsSeparator: true) }}
                            @if ($log->round_trip)
                                <x-status-badge tone="ghost" size="xs" class="ml-1">{{ __('hin/rück') }}</x-status-badge>
                            @endif
                        </td>
                        <td>
                            <x-status-badge tone="ghost" size="sm">{{ $log->vehicle->label() }}</x-status-badge>
                        </td>
                        <td class="text-right">
                            {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $log->reimbursement_total, 2, withThousandsSeparator: true) }} €
                        </td>
                        <td class="max-w-xs truncate">{{ $log->purpose }}</td>
                        <td class="text-right">
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('travel-logs.edit', $log)"
                                        :label="__('Bearbeiten')" />
                            <x-action-form :action="route('travel-logs.per-diem.generate', $log)"
                                  :confirm="__('Verpflegungspauschale aus dieser Fahrt erzeugen?')"
                                  :confirm-label="__('Erzeugen')">
                                <x-icon-btn icon="restaurant_menu" tone="primary" type="submit"
                                            :label="__('Verpflegungspauschale erzeugen')" />
                            </x-action-form>
                            <x-action-form :action="route('travel-logs.destroy', $log)" method="DELETE"
                                  :confirm="__('Fahrt wirklich löschen?')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">directions_car</span>' :colspan="8" :title="__('Keine Fahrten im gewählten Zeitraum')" compact />
                @endforelse
            </x-table>
        </x-card>

        <x-pagination :paginator="$logs" standing />
    </x-index-page>
@endsection
