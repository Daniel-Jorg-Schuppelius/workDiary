@extends('layouts.app')

@section('title', __('Stellen'))
@section('nav-title', __('Stellen'))

@section('content')
<x-index-page :subtitle="__('Stellenbedarf, Veröffentlichungen und Bewerbungsstände.')">
    <x-slot:actions>
        @can('create', \App\Models\Applications\JobRequisition::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('recruiting.requisitions.create')"
                        show-label>{{ __('Stelle anlegen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    @include('applications.recruiting._tabs')

    <x-filter-bar :action="route('recruiting.requisitions.index')" :reset="route('recruiting.requisitions.index')">
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
                    <th>{{ __('Stelle') }}</th>
                    <th>{{ __('Abteilung') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Bewerbungen') }}</th>
                    <th>{{ __('Zielstart') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($requisitions as $requisition)
                <tr>
                    <td><a href="{{ route('recruiting.requisitions.show', $requisition) }}" class="link">{{ $requisition->title }}</a></td>
                    <td>{{ $requisition->department ?? '—' }}</td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$requisition->status}") }}</x-status-badge></td>
                    <td class="text-right">{{ $requisition->applications_count }}</td>
                    <td>{{ optional($requisition->target_start_on)->fdate() ?? '—' }}</td>
                    <td><x-icon-btn icon="visibility" :href="route('recruiting.requisitions.show', $requisition)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">work</span>' :colspan="6" :title="__('Keine Stellen — „Stelle anlegen" startet den ersten Bedarf.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$requisitions" standing />
</x-index-page>
@endsection
