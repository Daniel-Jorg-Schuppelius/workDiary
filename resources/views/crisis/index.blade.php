{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('layouts.app')

@section('title', __('Krisenakten'))
@section('nav-title', __('Krisenmanagement'))

@section('content')
<x-index-page :subtitle="__('Gemeinsames Lagebild über Vorfälle, Krisenstab, Kommunikation und Wiederanlauf.')">
    <x-slot:actions>
        @can('create', \App\Models\Crisis\CrisisCase::class)
            <x-icon-btn icon="add" tone="error" size="sm"
                        data-entry-modal-trigger
                        :href="route('crisis.create')"
                        show-label>{{ __('Krise melden') }}</x-icon-btn>
        @endcan
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi-tile :label="__('Aktive Krisen')" :value="$activeCount" />
        <x-kpi-tile :label="__('Überfällige Maßnahmen')" :value="$overdueActions" />
        <x-kpi-tile :label="__('Übungen fällig (30 Tage)')" :value="$openDecisionsExercises" />
    </div>

    <x-filter-bar :action="route('crisis.index')" :reset="route('crisis.index')">
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
                    <th>{{ __('Krise') }}</th>
                    <th>{{ __('Kategorie') }}</th>
                    <th>{{ __('Schweregrad') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Aktiviert') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @forelse ($cases as $case)
                <tr>
                    <td><a href="{{ route('crisis.show', $case) }}" class="link">{{ $case->title }}</a></td>
                    <td>{{ __("values.{$case->category}") }}</td>
                    <td><x-status-badge size="md" :tone="$case->severity === 'critical' ? 'error' : ($case->severity === 'major' ? 'warning' : 'outline')">{{ __("values.{$case->severity}") }}</x-status-badge></td>
                    <td><x-status-badge size="md" outline>{{ __("values.{$case->status}") }}</x-status-badge></td>
                    <td>{{ optional($case->activated_at)->fdatetime() ?? '—' }}</td>
                    <td class="text-right"><x-icon-btn icon="visibility" :href="route('crisis.show', $case)" :label="__('Anzeigen')" /></td>
                </tr>
            @empty
                <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">emergency_home</span>' :colspan="6" :title="__('Keine Krisenakten — hoffentlich bleibt es so.')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-pagination :paginator="$cases" standing />
</x-index-page>
@endsection
