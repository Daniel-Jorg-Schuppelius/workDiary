{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Strategische Ziele (MVP-942). Erwartet: $objectives, $canManage --}}
@extends('layouts.app')

@section('title', __('investment.objective.title'))
@section('nav-title', __('investment.objective.title'))

@section('content')
<x-index-page :subtitle="__('investment.objective.subtitle')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" size="sm" tone="primary" data-entry-modal-trigger :href="route('investments.objectives.create')" show-label>{{ __('investment.objective.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    @include('investments._tabs')
    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('investment.objective.field.title') }}</th>
                    <th>{{ __('investment.objective.field.owner') }}</th>
                    <th>{{ __('investment.objective.field.period') }}</th>
                    <th>{{ __('investment.objective.field.key_results') }}</th>
                    <th class="text-right">{{ __('investment.objective.field.cases') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($objectives as $objective)
                <tr>
                    <td><a class="link" href="{{ route('investments.objectives.show', $objective) }}">{{ $objective->title }}</a>@unless ($objective->is_active) <span class="wd-badge badge-ghost">{{ __('investment.objective.inactive') }}</span>@endunless</td>
                    <td>{{ $objective->owner?->name ?? '—' }}</td>
                    <td>{{ $objective->valid_from?->format('d.m.Y') ?? '—' }} – {{ $objective->valid_until?->format('d.m.Y') ?? '—' }}</td>
                    <td class="text-sm">
                        @foreach ($objective->keyResults as $kr)
                            <div>{{ $kr->label }}: {{ $kr->progress() === null ? '—' : $kr->progress() . ' %' }}</div>
                        @endforeach
                    </td>
                    <td class="text-right tabular-nums">{{ $objective->cases_count }}</td>
                </tr>
            @empty
                <x-table.empty icon="flag" :colspan="5" :title="__('investment.objective.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
