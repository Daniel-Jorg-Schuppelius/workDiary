{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Strategisches Ziel mit Portfolio (MVP-942). Erwartet: $objective, $portfolio, $canManage --}}
@extends('layouts.app')

@section('title', $objective->title)
@section('nav-title', __('investment.objective.title'))

@php
    $money = static fn (string $v): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true);
    $num = static fn (?string $v): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($v, 2, withThousandsSeparator: true);
@endphp

@section('content')
<x-index-page :subtitle="$objective->description"
              back-route="investments.objectives.index" :back-label="__('investment.objective.title')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('investments.objectives.edit', $objective)" show-label>{{ __('investment.objective.edit') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-card padding="p-0" :title="__('investment.objective.field.key_results')">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('investment.objective.field.label') }}</th>
                    <th class="text-right">{{ __('investment.objective.field.baseline_value') }}</th>
                    <th class="text-right">{{ __('investment.objective.field.current_value') }}</th>
                    <th class="text-right">{{ __('investment.objective.field.target_value') }}</th>
                    <th class="text-right">{{ __('investment.objective.field.progress') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($objective->keyResults as $kr)
                <tr>
                    <td>{{ $kr->label }}</td>
                    <td class="text-right tabular-nums">{{ $num($kr->baseline_value) }} {{ $kr->unit }}</td>
                    <td class="text-right tabular-nums">{{ $num($kr->current_value) }} {{ $kr->unit }}</td>
                    <td class="text-right tabular-nums">{{ $num($kr->target_value) }} {{ $kr->unit }}</td>
                    <td class="text-right tabular-nums">{{ $kr->progress() === null ? '—' : $kr->progress() . ' %' }}</td>
                </tr>
            @empty
                <x-table.empty icon="flag" :colspan="5" :title="__('investment.objective.no_key_results')" compact />
            @endforelse
        </x-table>
    </x-card>

    <x-card padding="p-0" class="mt-4" :title="__('investment.objective.portfolio', ['planned' => $money($portfolio['planned'])])">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('investment.program.field.case') }}</th>
                    <th>{{ __('investment.program.field.status') }}</th>
                    <th class="text-right">{{ __('investment.program.field.planned') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($portfolio['cases'] as $row)
                <tr>
                    <td><a class="link" href="{{ route('investments.show', $row['case']) }}">{{ $row['case']->title }}</a></td>
                    <td>{{ __('values.' . $row['case']->status) }}</td>
                    <td class="text-right tabular-nums">{{ $money($row['planned']) }}</td>
                </tr>
            @empty
                <x-table.empty icon="trending_up" :colspan="3" :title="__('investment.objective.no_cases')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
