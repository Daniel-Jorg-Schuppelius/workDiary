{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : suitability.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Eignungsmatrix (MVP-924). Erwartet: $requisition, $matrix, $competencies --}}
@extends('layouts.app')

@section('title', __('recruiting.suitability.title'))
@section('nav-title', __('recruiting.suitability.title'))

@section('content')
<x-index-page :subtitle="__('recruiting.suitability.subtitle', ['title' => $requisition->title])"
              :back="route('recruiting.requisitions.show', $requisition)" :back-label="$requisition->title">

    <x-card :title="__('recruiting.suitability.requirements')" icon="checklist">
        @if ($matrix['requirements']->isEmpty())
            <p class="text-sm text-muted">{{ __('recruiting.suitability.no_requirements') }}</p>
        @else
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($matrix['requirements'] as $requirement)
                    <li class="flex items-center justify-between gap-2 py-1">
                        <span>{{ $requirement->competency?->name }} — {{ __('recruiting.suitability.required', ['level' => $requirement->required_level]) }}</span>
                        @can('update', $requisition)
                            <x-action-form :action="route('recruiting.requisitions.suitability.requirements.destroy', [$requisition, $requirement])" method="DELETE"
                                           :confirm="__('recruiting.suitability.confirm_remove')" confirm-icon="delete" confirm-tone="error" :confirm-label="__('recruiting.suitability.remove')">
                                <x-icon-btn icon="delete" tone="error" size="xs" type="submit" :label="__('recruiting.suitability.remove')" />
                            </x-action-form>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
        @can('update', $requisition)
            @if ($competencies->isEmpty())
                <p class="mt-3 text-sm text-muted">{{ __('recruiting.suitability.no_competencies') }}</p>
            @else
                <form method="POST" action="{{ route('recruiting.requisitions.suitability.requirements.store', $requisition) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <select name="competency_id" class="select select-sm select-bordered w-64" aria-label="{{ __('recruiting.suitability.competency') }}" required>
                        @foreach ($competencies as $competency)
                            <option value="{{ $competency->sqid }}">{{ $competency->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="required_level" min="1" max="10" value="3" class="input input-sm input-bordered w-20" aria-label="{{ __('recruiting.suitability.level') }}" required>
                    <x-button type="submit" size="sm" icon="add">{{ __('recruiting.suitability.add') }}</x-button>
                </form>
            @endif
        @endcan
    </x-card>

    <x-card :title="__('recruiting.suitability.matrix')" icon="grid_view" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('recruiting.suitability.candidate') }}</th>
                    @foreach ($matrix['requirements'] as $requirement)
                        <th class="text-center">{{ $requirement->competency?->name }} <span class="text-muted">({{ $requirement->required_level }})</span></th>
                    @endforeach
                    <th class="text-right">{{ __('recruiting.suitability.gaps') }}</th>
                    <th class="text-right">{{ __('recruiting.suitability.score') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($matrix['rows'] as $row)
                <tr>
                    <td><a class="link" href="{{ route('recruiting.applications.show', $row['application']) }}">{{ $row['application']->candidate_name ?? '—' }}</a></td>
                    @foreach ($matrix['requirements'] as $requirement)
                        @php($level = $row['levels'][$requirement->competency_id] ?? null)
                        <td class="text-center tabular-nums {{ $level === null ? 'text-muted' : ($level >= $requirement->required_level ? 'text-success' : 'text-warning') }}">{{ $level ?? '—' }}</td>
                    @endforeach
                    <td class="text-right tabular-nums">{{ $row['gaps'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['score'] !== null ? $row['score'] . ' %' : '—' }}</td>
                </tr>
            @empty
                <x-table.empty icon="grid_view" :colspan="$matrix['requirements']->count() + 3" :title="__('recruiting.suitability.no_applications')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
