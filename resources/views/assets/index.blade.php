{{--
  Created on   : Sun May 24 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Objekte & Assets'))
@section('nav-title', __('Objekte & Assets'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('Objekte, Geräte und Anlagen des Mandanten verwalten.')">
        <x-slot:actions>
            @if ($canCreate)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('assets.create')"
                            show-label>{{ __('Asset anlegen') }}</x-icon-btn>
            @endif
        </x-slot:actions>

        <div class="grid gap-3 sm:grid-cols-3">
            <x-kpi-tile :label="__('Assets gesamt')" :value="$kpis['total']" tone="neutral" />
            <x-kpi-tile :label="__('In Wartung/Reparatur')" :value="$kpis['maintenance']" :tone="$kpis['maintenance'] > 0 ? 'warning' : 'neutral'" />
            <x-kpi-tile :label="__('Gesperrt')" :value="$kpis['blocked']" :tone="$kpis['blocked'] > 0 ? 'error' : 'neutral'" />
        </div>

        <x-filter-bar :action="route('assets.index')"
                      :reset="$hasActiveFilters ? route('assets.index') : null">
            <x-filter-field :label="__('Suche')" for="asset-q" class="flex-1 min-w-60">
                <input id="asset-q" type="search" name="q"
                       value="{{ $activeFilters['q'] }}"
                       placeholder="{{ __('Asset-Nr., Name, Seriennummer, Standort') }}"
                       class="input input-sm input-bordered w-full">
            </x-filter-field>

            <x-filter-field :label="__('Typ')" for="asset-class" class="min-w-40">
                <select id="asset-class" name="class" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('Alle') }}</option>
                    @foreach ($classOptions as $value => $label)
                        <option value="{{ $value }}" @selected($activeFilters['class'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>

            <x-filter-field :label="__('Status')" for="asset-status" class="min-w-40">
                <select id="asset-status" name="status" class="select select-sm select-bordered w-full">
                    <option value="all">{{ __('Alle') }}</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($activeFilters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filter-field>
        </x-filter-bar>

        @if ($hasActiveFilters || $assets->total() > 0)
            <div class="flex flex-wrap items-center gap-2 px-1 text-sm text-base-content/70">
                <span>{{ trans_choice(':count Ergebnis|:count Ergebnisse', $assets->total(), ['count' => $assets->total()]) }}</span>
                @foreach ($activeFilterChips as $chip)
                    <x-status-badge size="sm" outline>{{ $chip }}</x-status-badge>
                @endforeach
            </div>
        @endif

        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('assets.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="array_filter(['q' => $activeFilters['q'], 'class' => $activeFilters['class'] === 'all' ? null : $activeFilters['class'], 'status' => $activeFilters['status'] === 'all' ? null : $activeFilters['status']])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="asset_no">{{ __('Asset-Nr.') }}</x-table.th>
                    <x-table.th sort="asset_class">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort="name" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort="serial_no">{{ __('Seriennummer') }}</x-table.th>
                    <x-table.th sort="location_text">{{ __('Standort') }}</x-table.th>
                    <th>{{ __('Kunde') }}</th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($assets as $asset)
                @php
                    $assetClassValue = $asset->asset_class instanceof \BackedEnum ? $asset->asset_class->value : (string) $asset->asset_class;
                    $assetStatusValue = $asset->status instanceof \BackedEnum ? $asset->status->value : (string) $asset->status;
                    $isBlocked = $assetStatusValue === \App\Enums\Asset\AssetStatus::Blocked->value;
                @endphp
                <tr class="hover">
                    <td class="font-mono text-xs">
                        <a href="{{ route('assets.show', $asset) }}" class="link link-hover">{{ $asset->asset_no }}</a>
                    </td>
                    <td><x-status-badge tone="ghost" outline>{{ $classOptions[$assetClassValue] ?? $assetClassValue }}</x-status-badge></td>
                    <td>
                        <a href="{{ route('assets.show', $asset) }}" class="link link-hover font-medium">{{ $asset->name }}</a>
                    </td>
                    <td class="text-base-content/70">{{ $asset->serial_no ?: '—' }}</td>
                    <td class="text-base-content/70">{{ $asset->location_text ?: '—' }}</td>
                    <td class="text-base-content/70">{{ $asset->customer?->name ?: '—' }}</td>
                    <td>
                        <x-status-badge :tone="$isBlocked ? 'error' : 'ghost'" :outline="! $isBlocked">{{ $statusOptions[$assetStatusValue] ?? $assetStatusValue }}</x-status-badge>
                    </td>
                    <td class="text-right">
                        <x-icon-btn icon="open_in_new" :href="route('assets.show', $asset)" :label="__('Details')" />
                    </td>
                </tr>
            @empty
                <x-table.empty :colspan="8"
                               :title="__('Keine Assets gefunden')"
                               :message="$hasActiveFilters ? __('Für die aktuellen Filter wurden keine Assets gefunden.') : __('Es sind noch keine Assets erfasst.')" />
            @endforelse
        </x-table>

        <x-pagination :paginator="$assets" />
    </x-index-page>
@endsection
