{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wettkämpfe (Feature 159, MVP-855): Liste mit Sportart, Disziplinen und Meldungen. --}}
@extends('layouts.app')
@section('title', __('club.competitions.title.index'))
@section('nav-title', __('club.competitions.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.competitions.subtitle.index')">
    <x-slot:actions>
        <x-icon-btn icon="fact_check" tone="outline" size="sm" :href="route('club.requirements.index')" show-label>{{ __('club.competitions.title.requirements') }}</x-icon-btn>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.competitions.create')" show-label>{{ __('club.competitions.action.create') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.competitions" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.competitions.index')" :reset="route('club.competitions.index')">
        <x-filter-field :label="__('club.events.field.period')" for="flt-period">
            <select id="flt-period" name="period" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="upcoming" @selected($filters['period'] === 'upcoming')>{{ __('club.events.filter.period_upcoming') }}</option>
                <option value="range" @selected($filters['period'] === 'range')>{{ __('club.events.filter.period_range') }}</option>
                <option value="past" @selected($filters['period'] === 'past')>{{ __('club.events.filter.period_past') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('club.teams.field.profile')" for="flt-profile">
            <select id="flt-profile" name="profile" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="">{{ __('club.competitions.filter.all_profiles') }}</option>
                @foreach ($profiles as $profile)
                    <option value="{{ $profile->sqid }}" @selected($filters['profile'] === $profile->sqid)>{{ $profile->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.events.field.starts') }}</th>
                <th>{{ __('club.events.field.title') }}</th>
                <th>{{ __('club.teams.field.profile') }}</th>
                <th>{{ __('club.competitions.field.disciplines') }}</th>
                <th>{{ __('club.competitions.field.venue') }}</th>
                <th class="text-center">{{ __('club.competitions.field.entries') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($events as $event)
            @php($competition = $event->clubCompetition)
            <tr class="hover {{ $event->cancelled_at ? 'opacity-60' : '' }}">
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $event->started_at->orgTz()->format('d.m.Y H:i') }}</td>
                <td class="font-medium"><a href="{{ route('club.competitions.show', $event) }}" class="link link-hover">{{ $event->title }}</a></td>
                <td class="text-sm">{{ $competition?->profile?->name ?? '–' }}</td>
                <td class="text-sm">{{ count($competition?->disciplines ?? []) }}</td>
                <td class="text-sm">{{ $competition?->venue ?? $event->rooms->pluck('name')->implode(', ') ?: '–' }}</td>
                <td class="text-center text-sm tabular-nums">{{ $event->entries_count }}</td>
                <td><x-status-badge :tone="$event->status->tone()" size="sm">{{ $event->status->label() }}</x-status-badge></td>
                <td class="text-right"><x-icon-btn icon="visibility" :href="route('club.competitions.show', $event)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="emoji_events" :colspan="8" :title="__('club.competitions.empty.index')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$events" standing />
</x-index-page>
@endsection
