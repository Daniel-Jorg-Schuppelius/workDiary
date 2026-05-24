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

    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('Auftragstyp') }}</th>
                <th>{{ __('Pflicht-Domain') }}</th>
                <th>{{ __('Phase') }}</th>
                <th>{{ __('Schweregrad') }}</th>
                <th>{{ __('Anzahl') }}</th>
                <th>{{ __('Bedingung') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($requirements as $requirement)
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
        @empty
            <tr>
                <td colspan="7" class="text-center text-base-content/60 py-6">{{ __('Noch keine Pflichtregeln vorhanden.') }}</td>
            </tr>
        @endforelse
    </x-table>
</x-page-shell>
@endsection
