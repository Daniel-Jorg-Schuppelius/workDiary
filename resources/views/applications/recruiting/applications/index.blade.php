{{--
  Created on   : Sun Jul 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Bewerbungen') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('Bewerbungen'))

@section('content')
<x-index-page :subtitle="__('Bewerberpipeline — Zugriff nur für den Personalbereich (recruiting.*).')">
    <x-slot:actions>
        @can('create', \App\Models\Applications\JobApplication::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('recruiting.applications.create')"
                        show-label>{{ __('Bewerbung erfassen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @include('applications.recruiting._tabs')

    <x-filter-bar :action="route('recruiting.applications.index')" :reset="route('recruiting.applications.index')">
        <x-filter-field :label="__('Status')" for="app-status" class="shrink-0">
            <select id="app-status" name="status" class="select select-sm select-bordered w-44" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Alle Status') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __("values.$s") }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-card padding="p-0">
        <x-table table-sort="server"
                 :route="route('recruiting.applications.index')"
                 :current-sort="$sort ?? null"
                 :current-dir="$dir ?? 'desc'"
                 :sort-params="[]"
                 bare>
            <x-slot:head>
                <tr>
                    <x-table.th sort="candidate">{{ __('Kandidat') }}</x-table.th>
                    <x-table.th>{{ __('Stelle') }}</x-table.th>
                    <x-table.th>{{ __('Quelle') }}</x-table.th>
                    <x-table.th sort="status">{{ __('Status') }}</x-table.th>
                    <x-table.th sort="received">{{ __('Eingegangen') }}</x-table.th>
                    <x-table.th>{{ __('Löschvormerkung') }}</x-table.th>
                    <x-table.th></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($applications as $application)
                <tr>
                    <td>{{ $application->isAnonymized() ? __('(anonymisiert)') : ($application->candidate_name ?? '—') }}</td>
                    <td>{{ $application->requisition->title ?? '—' }}</td>
                    <td>{{ __("values.{$application->source}") }}</td>
                    <td><x-status-badge :tone="$application->statusTone()" size="sm">{{ __("values.{$application->status}") }}</x-status-badge></td>
                    <td class="tabular-nums">{{ optional($application->received_at)->fdate() ?? '—' }}</td>
                    <td class="tabular-nums">{{ optional($application->retention_until)->fdate() ?? '—' }}</td>
                    <td class="text-right"><x-icon-btn icon="visibility" tone="ghost" size="xs" :href="route('recruiting.applications.show', $application)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">person_search</span>' :colspan="7" :title="__('Keine Bewerbungen vorhanden.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$applications" standing />
</x-index-page>
@endsection
