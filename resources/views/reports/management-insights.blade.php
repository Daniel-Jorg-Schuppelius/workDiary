{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : management-insights.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Management-Auswertung (MVP-926). Erwartet: $groups (Collection kind → list<EarlyWarning>), $trainingNeeds --}}
@extends('layouts.app')

@section('title', __('reporting.management.title'))
@section('nav-title', __('reporting.management.title'))

@section('content')
<x-index-page :subtitle="__('reporting.management.subtitle')">
    <x-card :title="__('reporting.management.problems')" icon="repeat">
        @if ($groups->isEmpty())
            <x-empty-state compact icon="check_circle" :title="__('reporting.warning.widget.none')" />
        @else
            @foreach ($groups as $kind => $warnings)
                <h3 class="mt-3 text-sm font-semibold">{{ __('reporting.management.kind.' . $kind) }} <span class="text-muted">({{ $warnings->count() }})</span></h3>
                <ul class="mt-1 divide-y divide-base-300 text-sm">
                    @foreach ($warnings as $warning)
                        <li class="py-2">
                            @if ($warning->url)
                                <a href="{{ $warning->url }}" class="link link-hover font-medium">{{ __($warning->title, $warning->params) }}</a>
                            @else
                                <span class="font-medium">{{ __($warning->title, $warning->params) }}</span>
                            @endif
                            <p class="text-xs text-muted">{{ __($warning->detail, $warning->params) }}</p>
                            <p class="mt-1 text-xs"><x-icon name="lightbulb" class="text-sm" /> {{ __($warning->recommendation) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        @endif
    </x-card>

    <x-card :title="__('reporting.management.training')" icon="school" padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('reporting.management.competency') }}</th>
                    <th class="text-right">{{ __('reporting.management.people') }}</th>
                    <th class="text-right">{{ __('reporting.management.average_gap') }}</th>
                    <th>{{ __('reporting.management.courses') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($trainingNeeds as $row)
                <tr>
                    <td>{{ $row['competency'] }}</td>
                    <td class="text-right tabular-nums">{{ $row['people'] }}</td>
                    <td class="text-right tabular-nums">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($row['average_gap'], 1) }}</td>
                    <td>{{ $row['courses'] !== [] ? implode(', ', $row['courses']) : __('reporting.management.no_course') }}</td>
                </tr>
            @empty
                <x-table.empty icon="school" :colspan="4" :title="__('reporting.management.no_training')" compact />
            @endforelse
        </x-table>
    </x-card>
</x-index-page>
@endsection
