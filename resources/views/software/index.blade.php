@extends('layouts.app')

@section('title', __('Software'))
@section('nav-title', __('Software'))

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $softwareItems */
    /** @var array<string, string> $kindOptions */
    /** @var array<string, string> $licenseTypeOptions */
    /** @var array{q: string, kind: string} $activeFilters */
@endphp

@section('content')
<x-index-page :subtitle="__('Software-Katalog (Betriebssysteme, Anwendungen, Lizenzen) verwalten.')">
    <x-slot:actions>
        @if ($canCreate ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('software.create')"
                        show-label>{{ __('Software anlegen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('software.index')" :reset="route('software.index')">
        <input type="text" name="q" value="{{ $activeFilters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        <select name="kind" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Art') }}">
            <option value="">{{ __('Alle Arten') }}</option>
            @foreach ($kindOptions as $value => $label)
                <option value="{{ $value }}" @selected($activeFilters['kind'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($softwareItems->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">apps</span>' />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Hersteller') }}</th>
                    <th>{{ __('Art') }}</th>
                    <th>{{ __('Lizenz') }}</th>
                    <th class="text-right">{{ __('Installationen') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($softwareItems as $software)
                <tr class="hover">
                    <td class="font-medium">
                        {{ $software->name }}
                        @if (! $software->is_active)
                            <span class="badge badge-ghost badge-xs ml-1">{{ __('inaktiv') }}</span>
                        @endif
                        @if ($software->default_version)
                            <div class="text-xs text-base-content/60">{{ $software->default_version }}</div>
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ $software->vendor ?? '—' }}</td>
                    <td class="text-base-content/70">{{ $kindOptions[$software->kind->value] ?? $software->kind->value }}</td>
                    <td class="text-base-content/70">{{ $licenseTypeOptions[$software->license_type->value] ?? $software->license_type->value }}</td>
                    <td class="text-right tabular-nums">{{ $software->installations_count }}</td>
                    <td class="text-right">
                        @can('update', $software)
                            <x-icon-btn icon="edit" size="sm"
                                        data-entry-modal-trigger
                                        :href="route('software.edit', $software)" />
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>

        <div class="mt-4">
            {{ $softwareItems->links() }}
        </div>
    @endif
</x-index-page>
@endsection
