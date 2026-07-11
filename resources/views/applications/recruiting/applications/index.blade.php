@extends('layouts.app')

@section('title', __('Bewerbungen'))
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
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __("values.$s") }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('Kandidat') }}</th>
                    <th>{{ __('Stelle') }}</th>
                    <th>{{ __('Quelle') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Eingegangen') }}</th>
                    <th>{{ __('Löschvormerkung') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($applications as $application)
                <tr>
                    <td>{{ $application->isAnonymized() ? __('(anonymisiert)') : ($application->candidate_name ?? '—') }}</td>
                    <td>{{ $application->requisition->title ?? '—' }}</td>
                    <td>{{ __("values.{$application->source}") }}</td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$application->status}") }}</x-status-badge></td>
                    <td>{{ optional($application->received_at)->fdate() ?? '—' }}</td>
                    <td>{{ optional($application->retention_until)->fdate() ?? '—' }}</td>
                    <td><x-icon-btn icon="visibility" :href="route('recruiting.applications.show', $application)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">person_search</span>' :colspan="7" :title="__('Keine Bewerbungen vorhanden.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$applications" standing />
</x-index-page>
@endsection
