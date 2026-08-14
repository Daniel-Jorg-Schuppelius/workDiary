{{--
  Created on   : Sat May 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Pflichtregeln'))
@section('nav-title', __('Pflichtregeln'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page overflow="clip" :subtitle="__('Pflichtklassifikationen pro Auftragstyp für :org verwalten.', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="add" tone="primary" size="sm"
                    data-entry-modal-trigger
                    :href="route('admin.classification-requirements.create')"
                    show-label>{{ __('Pflichtregel anlegen') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('admin.classification-requirements.index')" :reset="route('admin.classification-requirements.index')">
        <input type="text"
               name="q"
               value="{{ $activeFilters['q'] ?? '' }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}"
               aria-label="{{ __('Suche') }}" />

        <select name="domain" class="select select-sm select-bordered w-32 shrink-0" aria-label="{{ __('Pflicht-Domain') }}" title="{{ __('Pflicht-Domain') }}">
            <option value="all" @selected(($activeFilters['domain'] ?? 'all') === 'all')>{{ __('Domain') }}</option>
            @foreach ($domainLabels as $domainCode => $domainLabel)
                @continue($domainCode === \App\Enums\Classification\ClassificationDomain::EntryType->value)
                <option value="{{ $domainCode }}" @selected(($activeFilters['domain'] ?? 'all') === $domainCode)>{{ $domainLabel }}</option>
            @endforeach
        </select>

        <select name="phase" class="select select-sm select-bordered w-28 shrink-0" aria-label="{{ __('Phase') }}" title="{{ __('Phase') }}">
            <option value="all" @selected(($activeFilters['phase'] ?? 'all') === 'all')>{{ __('Phase') }}</option>
            @foreach ($phaseLabels as $phaseCode => $phaseLabel)
                <option value="{{ $phaseCode }}" @selected(($activeFilters['phase'] ?? 'all') === $phaseCode)>{{ $phaseLabel }}</option>
            @endforeach
        </select>

        <select name="condition" class="select select-sm select-bordered w-28 shrink-0" aria-label="{{ __('Bedingung') }}" title="{{ __('Bedingung') }}">
            <option value="all" @selected(($activeFilters['condition'] ?? 'all') === 'all')>{{ __('Bedingung') }}</option>
            @foreach ($conditionOptions as $conditionCode => $conditionLabel)
                <option value="{{ $conditionCode }}" @selected(($activeFilters['condition'] ?? 'all') === $conditionCode)>{{ $conditionLabel }}</option>
            @endforeach
        </select>

        <select name="note" class="select select-sm select-bordered w-28 shrink-0" aria-label="{{ __('Hinweis') }}" title="{{ __('Hinweis') }}">
            <option value="all" @selected(($activeFilters['note'] ?? 'all') === 'all')>{{ __('Hinweis') }}</option>
            @foreach ($noteOptions as $noteCode => $noteLabel)
                <option value="{{ $noteCode }}" @selected(($activeFilters['note'] ?? 'all') === $noteCode)>{{ $noteLabel }}</option>
            @endforeach
        </select>

        <select name="allow_multi" class="select select-sm select-bordered w-28 shrink-0" aria-label="{{ __('Mehrfachauswahl') }}" title="{{ __('Mehrfachauswahl') }}">
            <option value="all" @selected(($activeFilters['allow_multi'] ?? 'all') === 'all')>{{ __('Mehrfach') }}</option>
            @foreach ($allowMultiOptions as $allowMultiCode => $allowMultiLabel)
                <option value="{{ $allowMultiCode }}" @selected(($activeFilters['allow_multi'] ?? 'all') === $allowMultiCode)>{{ $allowMultiLabel }}</option>
            @endforeach
        </select>

        <select name="max_count" class="select select-sm select-bordered w-24 shrink-0" aria-label="{{ __('Maximalanzahl') }}" title="{{ __('Maximalanzahl') }}">
            <option value="all" @selected(($activeFilters['max_count'] ?? 'all') === 'all')>{{ __('Limit') }}</option>
            @foreach ($maxCountOptions as $maxCountCode => $maxCountLabel)
                <option value="{{ $maxCountCode }}" @selected(($activeFilters['max_count'] ?? 'all') === $maxCountCode)>{{ $maxCountLabel }}</option>
            @endforeach
        </select>

        <select name="severity" class="select select-sm select-bordered w-28 shrink-0" aria-label="{{ __('Schweregrad') }}" title="{{ __('Schweregrad') }}">
            <option value="all" @selected(($activeFilters['severity'] ?? 'all') === 'all')>{{ __('Schwere') }}</option>
            @foreach ($severityLabels as $severityCode => $severityLabel)
                <option value="{{ $severityCode }}" @selected(($activeFilters['severity'] ?? 'all') === $severityCode)>{{ $severityLabel }}</option>
            @endforeach
        </select>

        <select name="sort" class="select select-sm select-bordered w-32 shrink-0" aria-label="{{ __('Sortierung') }}" title="{{ __('Sortierung') }}">
            @foreach ($sortOptions as $sortCode => $sortLabel)
                <option value="{{ $sortCode }}" @selected(($activeFilters['sort'] ?? 'entry_type_code') === $sortCode)>{{ $sortLabel }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    <div class="flex flex-wrap items-center gap-3 px-1">
        <span class="text-sm font-medium">
            {{ trans_choice(':count Pflichtregel angezeigt|:count Pflichtregeln angezeigt', $requirements->count(), ['count' => $requirements->count()]) }}
        </span>
        @if ($hasActiveFilters)
            <span class="text-sm font-medium">{{ __('Aktive Filter') }}</span>
            @foreach ($activeFilterChips as $chip)
                <x-status-badge size="sm" outline>{{ $chip }}</x-status-badge>
            @endforeach
        @endif
    </div>

    @if ($requirements->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">rule</span>' />
    @else
        <x-table scroll="flex" :pinRows="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Auftragstyp') }}</th>
                    <th>{{ __('Pflicht-Domain') }}</th>
                    <th>{{ __('Phase') }}</th>
                    <th>{{ __('Schweregrad') }}</th>
                    <th>{{ __('Anzahl') }}</th>
                    <th>{{ __('Mehrfach') }}</th>
                    <th>{{ __('Limit') }}</th>
                    <th>{{ __('Hinweis') }}</th>
                    <th>{{ __('Bedingung') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($requirements as $requirement)
                <tr>
                    <td class="font-mono text-sm">{{ $requirement->entry_type_code }}</td>
                    <td>{{ $domainLabels[$requirement->required_domain] ?? $requirement->required_domain }}</td>
                    <td>{{ $requirement->enforce_phase->label() }}</td>
                    <td>
                        <x-status-badge size="xs" :tone="$requirement->severity->value === 'hard' ? 'error' : 'warning'">
                            {{ $requirement->severity->label() }}
                        </x-status-badge>
                    </td>
                    <td>{{ $requirement->min_count }}@if ($requirement->max_count !== null) - {{ $requirement->max_count }}@endif</td>
                    <td>
                        <x-status-badge size="xs" :tone="$requirement->allow_multi ? 'info' : 'ghost'">
                            {{ $requirement->allow_multi ? __('Ja') : __('Nein') }}
                        </x-status-badge>
                    </td>
                    <td>
                        @if ($requirement->max_count !== null)
                            <x-status-badge size="xs" tone="ghost" outline>{{ __('Begrenzt') }}</x-status-badge>
                        @else
                            <span class="text-base-content/50">{{ __('Offen') }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($requirement->note)
                            <span>{{ $requirement->note }}</span>
                        @else
                            <span class="text-base-content/50">{{ __('—') }}</span>
                        @endif
                    </td>
                    <td>
                        @if ($requirement->only_if_json)
                            <pre class="text-xs whitespace-pre-wrap">{{ json_encode($requirement->only_if_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        @else
                            <span class="text-base-content/50">{{ __('Immer') }}</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <x-icon-btn icon="edit" size="xs"
                                    data-entry-modal-trigger
                                    :href="route('admin.classification-requirements.edit', $requirement)"
                                    :title="__('Bearbeiten')" />
                        <form method="POST" action="{{ route('admin.classification-requirements.destroy', $requirement) }}" class="inline">
                            @csrf @method('DELETE')
                            <x-icon-btn type="submit" icon="delete" size="xs" tone="error"
                                        :title="__('Löschen')"
                                        data-confirm="{{ __('Pflichtregel wirklich löschen?') }}" />
                        </form>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
