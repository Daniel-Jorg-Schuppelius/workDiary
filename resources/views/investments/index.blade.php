@extends('layouts.app')

@section('title', __('Investitionen'))
@section('nav-title', __('Investitionen'))

@section('content')
<x-index-page :subtitle="__('Investitionsakten mit Varianten, Budgetantrag, Freigabe und Soll-Ist-Verfolgung.')">
    <x-slot:actions>
        <x-icon-btn icon="analytics" size="sm" :href="route('investments.report')" show-label>{{ __('Bericht') }}</x-icon-btn>
        @can('create', \App\Models\Investments\InvestmentCase::class)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('investments.create')"
                        show-label>{{ __('Investition erfassen') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <x-filter-bar :action="route('investments.index')" :reset="route('investments.index')">
        <select name="status" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Status') }}">
            <option value="">{{ __('Alle Status') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ __("values.$s") }}</option>
            @endforeach
        </select>
        <select name="category" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Kategorie') }}">
            <option value="">{{ __('Alle Kategorien') }}</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}" @selected(($filters['category'] ?? '') === $c)>{{ __("values.$c") }}</option>
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
                    <th>{{ __('Titel') }}</th>
                    <th>{{ __('Kategorie') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Kostenstelle') }}</th>
                    <th>{{ __('Verantwortlich') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($cases as $case)
                <tr>
                    <td><a href="{{ route('investments.show', $case) }}" class="link">{{ $case->title }}</a></td>
                    <td>{{ __("values.{$case->category}") }}</td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$case->status}") }}</x-status-badge></td>
                    <td>{{ $case->costCenterDisplay() ?? '—' }}</td>
                    <td>{{ $case->responsible->name ?? '—' }}</td>
                    <td><x-icon-btn icon="visibility" :href="route('investments.show', $case)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">trending_up</span>' :colspan="6" :title="__('Keine Investitionen — „Investition erfassen" startet die erste Akte.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$cases" standing />
</x-index-page>
@endsection
