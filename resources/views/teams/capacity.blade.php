{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : capacity.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kapazität je Team und Woche (MVP-940). Erwartet: $rows, $weeks, $openHeadcount --}}
@extends('layouts.app')

@section('title', __('hr.capacity.title'))
@section('nav-title', __('hr.capacity.title'))

@php
    $hours = static fn (int $minutes): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($minutes / 60, 0, withThousandsSeparator: true);
@endphp

@section('content')
<x-index-page :subtitle="__('hr.capacity.subtitle')">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('teams.index')" show-label>{{ __('Teams') }}</x-icon-btn>
    </x-slot:actions>
    <x-card padding="p-0">
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>{{ __('hr.capacity.team') }}</th>
                    @foreach ($rows[0]['weeks'] ?? [] as $week)
                        <th class="text-right">{{ __('hr.capacity.week', ['date' => $week['start']->format('d.m.')]) }}</th>
                    @endforeach
                </tr>
            </x-slot:head>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        {{ $row['team']?->name ?? '—' }}
                        <div class="text-xs text-muted">{{ __('hr.capacity.members', ['count' => $row['members']]) }}</div>
                    </td>
                    @foreach ($row['weeks'] as $week)
                        <td class="text-right tabular-nums {{ ($week['utilization'] ?? 0) > 100 ? 'text-error font-semibold' : '' }}">
                            {{ $hours($week['demand']) }} / {{ $hours($week['capacity']) }} h
                            <div class="text-xs text-muted">{{ $week['utilization'] === null ? '—' : $week['utilization'] . ' %' }}</div>
                        </td>
                    @endforeach
                </tr>
            @empty
                <x-table.empty icon="groups" :colspan="1 + $weeks" :title="__('hr.capacity.empty')" compact />
            @endforelse
        </x-table>
    </x-card>
    <p class="mt-2 text-xs text-muted">{{ __('hr.capacity.hint') }}@if ($openHeadcount > 0) {{ __('hr.capacity.open_requisitions', ['count' => $openHeadcount]) }}@endif</p>
</x-index-page>
@endsection
