{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Spieltage (Feature 159, MVP-852): Liste je Mannschaft/Saison mit Aufstellungsstand und Ergebnis. --}}
@extends('layouts.app')
@section('title', __('club.matches.title.index'))
@section('nav-title', __('club.matches.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.matches.subtitle.index')">
    <x-slot:actions>
        <x-icon-btn icon="upload_file" tone="outline" size="sm" :href="route('club.matches.proposals.index')" show-label>{{ __('club.matches.title.proposals') }}@if ($openProposals > 0) <span class="badge badge-warning badge-xs">{{ $openProposals }}</span>@endif</x-icon-btn>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.matches.create')" show-label>{{ __('club.matches.action.create') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.matches" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.matches.index')" :reset="route('club.matches.index')">
        <x-filter-field :label="__('club.events.field.period')" for="flt-period">
            <select id="flt-period" name="period" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="upcoming" @selected($filters['period'] === 'upcoming')>{{ __('club.events.filter.period_upcoming') }}</option>
                <option value="range" @selected($filters['period'] === 'range')>{{ __('club.events.filter.period_range') }}</option>
                <option value="past" @selected($filters['period'] === 'past')>{{ __('club.events.filter.period_past') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('club.teams.field.team')" for="flt-team">
            <select id="flt-team" name="team" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="">{{ __('club.teams.filter.all_teams') }}</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->sqid }}" @selected($filters['team'] === $team->sqid)>{{ $team->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('club.teams.field.season')" for="flt-season">
            <select id="flt-season" name="season" class="select select-sm select-bordered w-40 shrink-0" data-autosubmit>
                <option value="">{{ __('club.teams.filter.all_seasons') }}</option>
                @foreach ($seasons as $season)
                    <option value="{{ $season->sqid }}" @selected($filters['season'] === $season->sqid)>{{ $season->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.events.field.starts') }}</th>
                <th>{{ __('club.teams.field.team') }}</th>
                <th>{{ __('club.matches.field.opponent') }}</th>
                <th>{{ __('club.matches.field.competition') }}</th>
                <th>{{ __('club.matches.field.venue') }}</th>
                <th class="text-center">{{ __('club.matches.field.lineup') }}</th>
                <th class="text-center">{{ __('club.matches.field.result') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($events as $event)
            @php($match = $event->clubMatch)
            <tr class="hover {{ $event->cancelled_at ? 'opacity-60' : '' }}">
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $event->started_at->orgTz()->format('d.m.Y H:i') }}</td>
                <td class="text-sm">{{ $match?->team?->name ?? '–' }}@if ($match?->season) <span class="block text-xs text-muted">{{ $match->season->name }}</span>@endif</td>
                <td class="font-medium">
                    <a href="{{ route('club.matches.show', $event) }}" class="link link-hover">{{ $match?->opponent_name }}</a>
                    <span class="badge badge-ghost badge-xs">{{ $match?->is_home ? __('club.matches.label.home') : __('club.matches.label.away') }}</span>
                </td>
                <td class="text-sm">{{ $match?->competition ?? '–' }}</td>
                <td class="text-sm">{{ $match?->venue ?? $event->rooms->pluck('name')->implode(', ') ?: '–' }}</td>
                <td class="text-center">
                    @if ($match)
                        <x-status-badge :tone="$match->lineup_status->tone()" size="xs">{{ $match->lineup_status->label() }}</x-status-badge>
                        <span class="block text-xs tabular-nums text-muted">{{ $event->lineup_count }}</span>
                    @endif
                </td>
                <td class="text-center text-sm tabular-nums">{{ $match?->result_summary ?? '–' }}</td>
                <td><x-status-badge :tone="$event->status->tone()" size="sm">{{ $event->status->label() }}</x-status-badge></td>
                <td class="text-right"><x-icon-btn icon="visibility" :href="route('club.matches.show', $event)" :label="__('club.action.show')" /></td>
            </tr>
        @empty
            <x-table.empty icon="sports_soccer" :colspan="9" :title="__('club.matches.empty.index')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$events" standing />
</x-index-page>
@endsection
