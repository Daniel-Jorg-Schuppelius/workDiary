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
        <x-page-toolbar :title="__('Objekte & Assets')" :subtitle="__('Stammdaten, Status und Zuordnung im Überblick.')">
            <x-slot:actions>
                @if ($canCreate)
                    <x-icon-btn icon="add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('assets.create')"
                                show-label>{{ __('Asset') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>

        <x-card>
            <form method="GET" action="{{ route('assets.index') }}" class="grid gap-3 md:grid-cols-12 md:items-end">
                <label class="form-control md:col-span-6">
                    <span class="label-text">{{ __('Suche') }}</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ $activeFilters['q'] }}"
                        placeholder="{{ __('Asset-Nr., Name, Seriennummer, Standort') }}"
                        class="input input-bordered w-full"
                    >
                </label>

                <label class="form-control md:col-span-3">
                    <span class="label-text">{{ __('Typ') }}</span>
                    <select name="class" class="select select-bordered w-full">
                        <option value="all">{{ __('Alle') }}</option>
                        @foreach ($classOptions as $value => $label)
                            <option value="{{ $value }}" @selected($activeFilters['class'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control md:col-span-3">
                    <span class="label-text">{{ __('Status') }}</span>
                    <select name="status" class="select select-bordered w-full">
                        <option value="all">{{ __('Alle') }}</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($activeFilters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="md:col-span-12 flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Filtern') }}</button>
                    @if ($hasActiveFilters)
                        <a href="{{ route('assets.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurücksetzen') }}</a>
                    @endif
                    <span class="text-sm text-base-content/70">{{ trans_choice(':count Ergebnis|:count Ergebnisse', $assets->total(), ['count' => $assets->total()]) }}</span>
                </div>
            </form>

            @if ($hasActiveFilters)
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($activeFilterChips as $chip)
                        <span class="badge badge-outline">{{ $chip }}</span>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>{{ __('Asset-Nr.') }}</th>
                            <th>{{ __('Typ') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Seriennummer') }}</th>
                            <th>{{ __('Standort') }}</th>
                            <th>{{ __('Kunde') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            @php
                                $assetClassValue = $asset->asset_class instanceof \BackedEnum ? $asset->asset_class->value : (string) $asset->asset_class;
                                $assetStatusValue = $asset->status instanceof \BackedEnum ? $asset->status->value : (string) $asset->status;
                                $isBlocked = $assetStatusValue === \App\Enums\Asset\AssetStatus::Blocked->value;
                            @endphp
                            <tr>
                                <td class="font-mono text-xs">
                                    <a href="{{ route('assets.show', $asset) }}" class="link link-hover">{{ $asset->asset_no }}</a>
                                </td>
                                <td>{{ $classOptions[$assetClassValue] ?? $assetClassValue }}</td>
                                <td class="font-medium">
                                    <a href="{{ route('assets.show', $asset) }}" class="link link-hover">{{ $asset->name }}</a>
                                </td>
                                <td>{{ $asset->serial_no ?: '—' }}</td>
                                <td>{{ $asset->location_text ?: '—' }}</td>
                                <td>{{ $asset->customer?->name ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $isBlocked ? 'badge-error' : 'badge-outline' }}">{{ $statusOptions[$assetStatusValue] ?? $assetStatusValue }}</span>
                                </td>
                                <td class="text-right">
                                    <x-icon-btn icon="open_in_new" :href="route('assets.show', $asset)" :label="__('Details')" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-base-content/70">{{ __('Keine Assets gefunden.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $assets->links() }}</div>
        </x-card>
    </x-page-shell>
@endsection
