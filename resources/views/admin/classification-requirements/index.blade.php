@extends('layouts.app')

@section('title', __('Pflichtregeln'))
@section('nav-title', __('Pflichtregeln'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>
                <div>
                    <h1 class="text-xl font-semibold">{{ __('Pflichtregeln') }}</h1>
                    <p class="text-sm text-base-content/60">{{ __('Pflichtklassifikationen pro Auftragstyp für :org verwalten.', ['org' => $organization->name]) }}</p>
                </div>
            </x-slot:title>
            <x-slot:actions>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.classification-requirements.create')"
                            show-label>{{ __('Pflichtregel anlegen') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <form method="GET" action="{{ route('admin.classification-requirements.index') }}" class="grid grid-cols-1 md:grid-cols-7 gap-3 items-end">
            <label class="form-control md:col-span-2">
                <span class="label-text text-sm">{{ __('Suche') }}</span>
                <input type="text"
                       name="q"
                       value="{{ $activeFilters['q'] ?? '' }}"
                       class="input input-bordered w-full"
                      placeholder="{{ __('Auftragstyp, Domain, Hinweis oder Bedingung') }}" />
            </label>

            <label class="form-control">
                <span class="label-text text-sm">{{ __('Pflicht-Domain') }}</span>
                <select name="domain" class="select select-bordered w-full">
                    <option value="all" @selected(($activeFilters['domain'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                    @foreach ($domainLabels as $domainCode => $domainLabel)
                        @continue($domainCode === \App\Enums\Classification\ClassificationDomain::EntryType->value)
                        <option value="{{ $domainCode }}" @selected(($activeFilters['domain'] ?? 'all') === $domainCode)>{{ $domainLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="label-text text-sm">{{ __('Phase') }}</span>
                <select name="phase" class="select select-bordered w-full">
                    <option value="all" @selected(($activeFilters['phase'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                    @foreach ($phaseLabels as $phaseCode => $phaseLabel)
                        <option value="{{ $phaseCode }}" @selected(($activeFilters['phase'] ?? 'all') === $phaseCode)>{{ $phaseLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="label-text text-sm">{{ __('Bedingung') }}</span>
                <select name="condition" class="select select-bordered w-full">
                    <option value="all" @selected(($activeFilters['condition'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                    @foreach ($conditionOptions as $conditionCode => $conditionLabel)
                        <option value="{{ $conditionCode }}" @selected(($activeFilters['condition'] ?? 'all') === $conditionCode)>{{ $conditionLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="label-text text-sm">{{ __('Schweregrad') }}</span>
                <select name="severity" class="select select-bordered w-full">
                    <option value="all" @selected(($activeFilters['severity'] ?? 'all') === 'all')>{{ __('Alle') }}</option>
                    @foreach ($severityLabels as $severityCode => $severityLabel)
                        <option value="{{ $severityCode }}" @selected(($activeFilters['severity'] ?? 'all') === $severityCode)>{{ $severityLabel }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control">
                <span class="label-text text-sm">{{ __('Sortierung') }}</span>
                <select name="sort" class="select select-bordered w-full">
                    @foreach ($sortOptions as $sortCode => $sortLabel)
                        <option value="{{ $sortCode }}" @selected(($activeFilters['sort'] ?? 'entry_type_code') === $sortCode)>{{ $sortLabel }}</option>
                    @endforeach
                </select>
            </label>

            <div class="md:col-span-7 flex gap-2 md:justify-end">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Filtern') }}</button>
                <a href="{{ route('admin.classification-requirements.index') }}" class="btn btn-ghost btn-sm">{{ __('Zuruecksetzen') }}</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium">
                {{ trans_choice(':count Pflichtregel angezeigt|:count Pflichtregeln angezeigt', $requirements->count(), ['count' => $requirements->count()]) }}
            </span>
            @if ($hasActiveFilters)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium">{{ __('Aktive Filter') }}</span>
                    @foreach ($activeFilterChips as $chip)
                        <span class="badge badge-outline badge-sm">{{ $chip }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </x-card>

    @if ($requirements->isEmpty())
        <x-card>
            <p class="text-sm text-base-content/70">{{ __('Keine Pflichtregeln fuer den aktuellen Filter gefunden.') }}</p>
        </x-card>
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Auftragstyp') }}</th>
                    <th>{{ __('Pflicht-Domain') }}</th>
                    <th>{{ __('Phase') }}</th>
                    <th>{{ __('Schweregrad') }}</th>
                    <th>{{ __('Anzahl') }}</th>
                    <th>{{ __('Hinweis') }}</th>
                    <th>{{ __('Bedingung') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($requirements as $requirement)
                <tr>
                    <td class="font-mono text-sm">{{ $requirement->entry_type_code }}</td>
                    <td>{{ $domainLabels[$requirement->required_domain] ?? $requirement->required_domain }}</td>
                    <td>{{ $phaseLabels[$requirement->enforce_phase->value] ?? $requirement->enforce_phase->value }}</td>
                    <td>
                        <span class="badge badge-xs {{ $requirement->severity->value === 'hard' ? 'badge-error' : 'badge-warning' }}">
                            {{ $severityLabels[$requirement->severity->value] ?? $requirement->severity->value }}
                        </span>
                    </td>
                    <td>{{ $requirement->min_count }}@if ($requirement->max_count !== null) - {{ $requirement->max_count }}@endif</td>
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
</x-page-shell>
@endsection
