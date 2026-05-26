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

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar :subtitle="__('Objekte, Geräte und Anlagen des Mandanten verwalten.')">
                <x-slot:actions>
                    @if ($canCreate)
                        <x-icon-btn icon="add" tone="primary" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('assets.create')"
                                    show-label>{{ __('Asset anlegen') }}</x-icon-btn>
                    @endif
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

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
                    <span class="badge badge-outline badge-sm">{{ $chip }}</span>
                @endforeach
            </div>
        @endif

        <x-table table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('Asset-Nr.') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                    <x-table.th sort type="string" default>{{ __('Name') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Seriennummer') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Standort') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Kunde') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
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
                    <td class="text-base-content/70">{{ $classOptions[$assetClassValue] ?? $assetClassValue }}</td>
                    <td>
                        <a href="{{ route('assets.show', $asset) }}" class="link link-hover font-medium">{{ $asset->name }}</a>
                    </td>
                    <td class="text-base-content/70">{{ $asset->serial_no ?: '—' }}</td>
                    <td class="text-base-content/70">{{ $asset->location_text ?: '—' }}</td>
                    <td class="text-base-content/70">{{ $asset->customer?->name ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $isBlocked ? 'badge-error' : 'badge-outline' }} badge-sm">{{ $statusOptions[$assetStatusValue] ?? $assetStatusValue }}</span>
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

        @if ($assets->hasPages())
            <div class="px-1">
                {{ $assets->links() }}
            </div>
        @endif
    </x-page-shell>
@endsection
