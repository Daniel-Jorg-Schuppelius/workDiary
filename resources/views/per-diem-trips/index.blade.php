{{--
  Created on   : Fri May 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Verpflegungspauschalen'))
@section('nav-title', __('Verpflegungspauschalen'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Dienstreisen mit Verpflegungspauschalen erfassen und abrechnen.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('per-diem-trips.create')"
                        show-label>{{ __('Neue Reise') }}</x-icon-btn>
        </x-slot:actions>

        <x-filter-bar :action="route('per-diem-trips.index')" :reset="route('per-diem-trips.index')">
            <x-filter-field :label="__('Status')" for="pd-status">
                <select id="pd-status" name="status" class="select select-sm select-bordered shrink-0"
                        data-autosubmit>
                    <option value="">{{ __('Alle Status') }}</option>
                    @foreach ($statusOptions as $opt)
                        <option value="{{ $opt->value }}" @selected($statusFilter === $opt->value)>{{ $opt->label() }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <x-kpi-tile :label="__('Reisen im Zeitraum')" :value="(string) $totals['count']" />
            <x-kpi-tile :label="__('Pauschalen-Summe')"
                        :value="\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($totals['amount'], 2, withThousandsSeparator: true) . ' €'" />
            <x-kpi-tile :label="__('Offene Reisen')"
                        :value="(string) $totals['open']"
                        :tone="$totals['open'] > 0 ? 'warning' : 'ghost'" />
        </div>

        <x-table :zebra="true" table-sort="server"
                 :route="route('per-diem-trips.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="['status' => $statusFilter]"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="started_at" default>{{ __('Beginn') }}</x-table.th>
                    <x-table.th>{{ __('Ende') }}</x-table.th>
                    <x-table.th sort="location">{{ __('Ort') }}</x-table.th>
                    <x-table.th>{{ __('Zweck') }}</x-table.th>
                    <x-table.th align="right">{{ __('Pauschale') }}</x-table.th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($trips as $trip)
                <tr>
                    <td class="whitespace-nowrap">{{ $trip->started_at->fdatetime() }}</td>
                    <td class="whitespace-nowrap">{{ $trip->ended_at->fdatetime() }}</td>
                    <td>
                        <span class="inline-flex items-center gap-1">
                            <x-icon name="place" class="text-info" />
                            {{ $trip->location }}
                            <span class="text-xs text-muted">({{ $trip->country }})</span>
                        </span>
                    </td>
                    <td class="max-w-xs truncate">{{ $trip->purpose }}</td>
                    <td class="text-right whitespace-nowrap">
                        {{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $trip->totalAmount(), 2, withThousandsSeparator: true) }} €
                        <span class="text-xs text-muted ml-1">({{ $trip->days->count() }} {{ __('Tage') }})</span>
                    </td>
                    <td>
                        <x-status-badge :tone="$trip->status->tone()" size="sm">
                            {{ $trip->status->label() }}
                        </x-status-badge>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <x-icon-btn icon="visibility"
                                    :href="route('per-diem-trips.show', $trip)"
                                    :label="__('Anzeigen')" />
                        @can('update', $trip)
                            <x-icon-btn icon="edit"
                                        data-entry-modal-trigger
                                        :href="route('per-diem-trips.edit', $trip)"
                                        :label="__('Bearbeiten')" />
                        @endcan
                        @can('delete', $trip)
                            <x-action-form :action="route('per-diem-trips.destroy', $trip)" method="DELETE"
                                  :confirm="__('Reise wirklich löschen?')"
                                  :confirm-label="__('Löschen')">
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </x-action-form>
                        @endcan
                    </td>
                </tr>
            @empty
                <x-table.empty icon="restaurant_menu"
                               :colspan="7"
                               :title="__('Keine Reisen im gewählten Zeitraum')"
                               compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$trips" standing />
    </x-index-page>
@endsection
