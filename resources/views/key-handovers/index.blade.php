{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Schlüsselverwaltung'))
@section('nav-title', __('Schlüsselverwaltung'))
@section('wrapper-height-class', 'min-h-[calc(100dvh_-_var(--app-header-h))] lg:h-[calc(100dvh_-_var(--app-header-h))] lg:overflow-clip')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Schlüssel-Ausgaben & Rückgaben des Mandanten.')">
    <x-slot:actions>
        @if ($canCreate)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        :href="route('key-handovers.create')"
                        show-label>{{ __('Vorgang erfassen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('key-handovers.index')" :reset="route('key-handovers.index')">
        <input type="text" name="q" value="{{ $filters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Person') }}" aria-label="{{ __('Person') }}" />
        <select name="direction" class="select select-sm select-bordered w-32 shrink-0" aria-label="{{ __('Richtung') }}">
            <option value="">{{ __('Alle') }}</option>
            @foreach ($directionOptions as $val => $label)
                <option value="{{ $val }}" @selected($filters['direction'] === $val)>{{ $label }}</option>
            @endforeach
        </select>
        @if (! empty($filters['asset']))
            <input type="hidden" name="asset" value="{{ $filters['asset'] }}">
        @endif
    </x-filter-bar>

    @if ($handovers->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">key</span>' />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Zeitpunkt') }}</th>
                    <th>{{ __('Asset / Schlüssel') }}</th>
                    <th>{{ __('Richtung') }}</th>
                    <th>{{ __('Person') }}</th>
                    <th>{{ __('Erfasst von') }}</th>
                    <th>{{ __('Rückgabe bis') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($handovers as $h)
                <tr class="hover">
                    <td class="font-mono text-xs">{{ $h->occurred_at?->translatedFormat('d.m.Y H:i') }}</td>
                    <td>
                        @if ($h->asset)
                            <span class="material-symbols-outlined text-[14px] align-middle">key</span>
                            {{ $h->asset->name }}
                            <span class="text-base-content/50 text-xs">{{ $h->asset->asset_no }}</span>
                        @endif
                    </td>
                    <td>
                        <x-status-badge size="sm" :tone="$h->direction->value === 'out' ? 'warning' : 'success'">
                            {{ $h->direction->label() }}
                        </x-status-badge>
                    </td>
                    <td>
                        <div class="font-medium">{{ $h->person_name }}</div>
                        @if ($h->person_reference)
                            <div class="text-xs text-base-content/50">{{ $h->person_reference }}</div>
                        @endif
                    </td>
                    <td class="text-base-content/70 text-xs">{{ $h->handedBy?->name ?: $h->returnedTo?->name ?: '—' }}</td>
                    <td class="text-xs text-base-content/70">{{ $h->expected_return_at?->translatedFormat('d.m.Y') ?: '—' }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$handovers" />
    @endif
</x-index-page>
@endsection
