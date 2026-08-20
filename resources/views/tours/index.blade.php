{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Touren'))
@section('nav-title', __('Touren'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Touren und Routen für Außendienst-Einsätze erfassen.')">
        <x-slot:actions>
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('tours.create')"
                        show-label>{{ __('Neue Tour') }}</x-icon-btn>
        </x-slot:actions>

        @include('tours._view-tabs')

        <x-filter-bar :action="route('tours.index')" :reset="route('tours.index')">
            <x-filter-field :label="__('Status')" for="tours-status">
                <select id="tours-status" name="status" class="select select-bordered select-sm">
                    <option value="">{{ __('alle') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
            @if ($selectableUsers !== null)
                <x-filter-field :label="__('Fahrer')" for="tours-user">
                    <select id="tours-user" name="user" class="select select-bordered select-sm">
                        <option value="all">{{ __('alle') }}</option>
                        @foreach ($selectableUsers as $u)
                            <option value="{{ $u->sqid }}" @selected($targetUser?->sqid === $u->sqid)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </x-filter-field>
            @endif
        </x-filter-bar>

        <x-table :zebra="true" table-sort="server"
                 :route="route('tours.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="array_filter([
                     'from' => $from->toDateString(),
                     'to' => $to->toDateString(),
                     'user' => request('user') === 'all'
                         ? 'all'
                         : (request()->filled('user') ? $targetUser?->sqid : null),
                     'status' => $selectedStatus,
                 ], fn ($v) => $v !== null && $v !== '')"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="tour_date" default>{{ __('Datum') }}</x-table.th>
                    <th>{{ __('Fahrer') }}</th>
                    <th>{{ __('Fahrzeug') }}</th>
                    <x-table.th sort="name">{{ __('Name') }}</x-table.th>
                    <x-table.th sort="distance" align="right">{{ __('km') }}</x-table.th>
                    <x-table.th sort="duration" align="right">{{ __('min') }}</x-table.th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($tours as $tour)
                <tr>
                    <td>{{ $tour->tour_date?->fdate() }}</td>
                    <td>{{ $tour->user?->name }}</td>
                    <td>{{ $tour->vehicle?->license_plate ?? '—' }}</td>
                    <td>
                        <a href="{{ route('tours.show', $tour) }}" class="link">
                            {{ $tour->name ?? ('#' . $tour->id) }}
                        </a>
                    </td>
                    <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $tour->planned_distance_km, 2, withThousandsSeparator: true) }}</td>
                    <td class="text-right">{{ $tour->planned_duration_minutes }}</td>
                    <td><x-status-badge tone="ghost" size="sm">{{ $tour->status?->label() }}</x-status-badge></td>
                    <td class="text-right">
                        <x-icon-btn icon="edit"
                                    :href="route('tours.edit', $tour)"
                                    :label="__('Bearbeiten')" />
                        <x-action-form :action="route('tours.destroy', $tour)" method="DELETE"
                              :confirm="__('Tour wirklich löschen?')"
                              :confirm-label="__('Löschen')">
                            <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                        </x-action-form>
                    </td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">route</span>' :colspan="8" :title="__('Keine Touren im gewählten Zeitraum')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$tours" standing />
    </x-index-page>
@endsection
