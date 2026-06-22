{{--
  Created on   : Sun Jun 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('permit.title'))
@section('nav-title', __('permit.title'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $permits */
    /** @var array<string, string> $statusOptions */
    /** @var array{q: string, status: string} $activeFilters */
@endphp

@section('content')
<x-index-page overflow="clip" :subtitle="__('permit.subtitle')">
    <x-slot:actions>
        @if ($canCreate ?? false)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('permits.create')"
                        show-label>{{ __('permit.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('permits.index')" :reset="route('permits.index')">
        <input type="text" name="q" value="{{ $activeFilters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('permit.fields.status') }}">
            <option value="">{{ __('permit.filter.all_status') }}</option>
            @foreach ($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected($activeFilters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($permits->total() === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">verified</span>' />
    @else
        <x-table table-sort="server"
                 :route="route('permits.index')"
                 :current-sort="$sort"
                 :current-dir="$dir"
                 :sort-params="array_filter(['q' => $activeFilters['q'], 'status' => $activeFilters['status'] === 'all' ? null : $activeFilters['status']])"
                 scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <x-table.th sort="title" default>{{ __('permit.fields.title') }}</x-table.th>
                    <x-table.th sort="authority">{{ __('permit.fields.authority') }}</x-table.th>
                    <x-table.th sort="status">{{ __('permit.fields.status') }}</x-table.th>
                    <x-table.th sort="valid_until">{{ __('permit.fields.valid_until') }}</x-table.th>
                    <th>{{ __('permit.fields.event') }}</th>
                    <th class="text-right"></th>
                </tr>
            </x-slot:head>
            @foreach ($permits as $permit)
                <tr class="hover">
                    <td class="font-medium">
                        {{ $permit->title }}
                        @if ($permit->reference_no)
                            <div class="text-xs text-base-content/60">{{ $permit->reference_no }}</div>
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ $permit->authority ?? '—' }}</td>
                    <td>
                        <x-status-badge :tone="$permit->status->tone()" size="xs">{{ $permit->status->label() }}</x-status-badge>
                    </td>
                    <td class="text-base-content/70 tabular-nums">
                        @if ($permit->valid_until)
                            <span @class(['text-error font-medium' => $permit->isOverdue()])>{{ $permit->valid_until->format('d.m.Y') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-base-content/70">{{ $permit->event?->title ?? '—' }}</td>
                    <td class="text-right">
                        @can('update', $permit)
                            <x-icon-btn icon="edit" size="sm"
                                        data-entry-modal-trigger
                                        :href="route('permits.edit', $permit)" />
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$permits" />
    @endif
</x-index-page>
@endsection
