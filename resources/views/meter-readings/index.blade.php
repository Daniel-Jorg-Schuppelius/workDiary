{{--
  Created on   : Thu May 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Zählerstände'))
@section('nav-title', __('Zählerstände'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Erfasste Zählerstände & Verbrauchswerte.')">
    <x-slot:actions>
        @if ($canCreate)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('meter-readings.create')"
                        show-label>{{ __('Stand erfassen') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @if ($readings->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">speed</span>' />
    @else
        <x-filter-bar :action="route('meter-readings.index')" method="GET" :reset="route('meter-readings.index')">
            @if (!empty($filters['asset']))
                <input type="hidden" name="asset" value="{{ $filters['asset'] }}" />
            @endif
            <input type="text" name="q" value="{{ $search ?? '' }}"
                   class="input input-sm input-bordered w-48 shrink-0"
                   placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        </x-filter-bar>

        <x-table scroll="flex" :pinRows="true" table-sort="server"
                 :route="route('meter-readings.index')" :current-sort="$sort" :current-dir="$dir"
                 :sort-params="array_filter(['asset' => $filters['asset'] ?? null, 'q' => $search ?: null])">
            <x-slot:head>
                <tr>
                    <x-table.th sort="read_at">{{ __('Ablesezeit') }}</x-table.th>
                    <th>{{ __('Asset / Zähler') }}</th>
                    <x-table.th sort="value" align="right">{{ __('Stand') }}</x-table.th>
                    <x-table.th sort="consumption" align="right">{{ __('Verbrauch') }}</x-table.th>
                    <x-table.th sort="unit">{{ __('Einheit') }}</x-table.th>
                    <th>{{ __('Erfasst von') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($readings as $r)
                <tr class="hover">
                    <td class="font-mono text-xs">{{ $r->read_at?->translatedFormat('d.m.Y H:i') }}</td>
                    <td>
                        @if ($r->asset)
                            <span class="material-symbols-outlined text-[14px] align-middle">speed</span>
                            {{ $r->asset->name }}
                            <span class="text-base-content/50 text-xs">{{ $r->asset->asset_no }}</span>
                        @endif
                    </td>
                    <td class="text-right font-mono">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $r->value, 4, withThousandsSeparator: true) }}</td>
                    <td class="text-right font-mono text-base-content/70">
                        {{ $r->consumption !== null ? \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $r->consumption, 4, withThousandsSeparator: true) : '—' }}
                    </td>
                    <td>{{ $r->unit }}</td>
                    <td class="text-base-content/70 text-xs">{{ $r->readBy?->name ?: '—' }}</td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$readings" standing />
    @endif
</x-index-page>
@endsection
