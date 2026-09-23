{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Spielplan-Vorschläge (MVP-852): Import aus CSV/ICS; die Leitung prüft Gegner, Ort, Zeit und Dubletten vor der Übernahme. --}}
@extends('layouts.app')
@section('title', __('club.matches.title.proposals'))
@section('nav-title', __('club.matches.title.proposals'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.matches.subtitle.proposals')">
    <x-slot:actions>
        @if ($teams->isNotEmpty())
            <x-icon-btn icon="upload_file" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.matches.proposals.import.create', ['team' => $filters['team'] ?: null])" show-label>{{ __('club.matches.action.import') }}</x-icon-btn>
        @endif
        <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.matches.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
        <x-help-button topic="club.matches" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.matches.proposals.index')" :reset="route('club.matches.proposals.index')">
        <x-filter-field :label="__('club.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered w-40 shrink-0" data-autosubmit>
                <option value="open" @selected($filters['status'] === 'open')>{{ \App\Enums\Club\ClubProposalStatus::Open->label() }}</option>
                <option value="confirmed" @selected($filters['status'] === 'confirmed')>{{ \App\Enums\Club\ClubProposalStatus::Confirmed->label() }}</option>
                <option value="dismissed" @selected($filters['status'] === 'dismissed')>{{ \App\Enums\Club\ClubProposalStatus::Dismissed->label() }}</option>
                <option value="all" @selected($filters['status'] === 'all')>{{ __('club.filter.all') }}</option>
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
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.events.field.starts') }}</th>
                <th>{{ __('club.teams.field.team') }}</th>
                <th>{{ __('club.matches.field.opponent') }}</th>
                <th>{{ __('club.matches.field.competition') }}</th>
                <th>{{ __('club.matches.field.venue') }}</th>
                <th>{{ __('club.matches.field.source') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($proposals as $proposal)
            <tr class="hover {{ $proposal->isOpen() ? '' : 'opacity-70' }}">
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $proposal->starts_at->orgTz()->format('d.m.Y H:i') }}@if ($proposal->ends_at)–{{ $proposal->ends_at->orgTz()->format('H:i') }}@endif</td>
                <td class="text-sm">{{ $proposal->team?->name }}</td>
                <td class="font-medium">{{ $proposal->opponent_name }} <span class="badge badge-ghost badge-xs">{{ $proposal->is_home ? __('club.matches.label.home') : __('club.matches.label.away') }}</span>
                    @if ($proposal->duplicateEvent)
                        <span class="block text-xs text-warning">{{ __('club.matches.label.duplicate_of', ['title' => $proposal->duplicateEvent->title]) }}</span>
                    @endif
                </td>
                <td class="text-sm">{{ $proposal->competition ?? '–' }}</td>
                <td class="text-sm">{{ $proposal->venue ?? '–' }}</td>
                <td class="text-sm">{{ $proposal->source->label() }}<span class="block text-xs text-muted">{{ $proposal->importedBy?->name }}</span></td>
                <td>
                    <x-status-badge :tone="$proposal->status === \App\Enums\Club\ClubProposalStatus::Open ? 'warning' : ($proposal->status === \App\Enums\Club\ClubProposalStatus::Confirmed ? 'success' : 'ghost')" size="sm">{{ $proposal->status->label() }}</x-status-badge>
                    @if ($proposal->event)<a href="{{ route('club.matches.show', $proposal->event) }}" class="link link-hover block text-xs">{{ $proposal->event->title }}</a>@endif
                </td>
                <td class="text-right">
                    @if ($proposal->isOpen())
                        <x-icon-btn icon="check" tone="primary" size="xs" data-entry-modal-trigger :href="route('club.matches.proposals.accept.edit', $proposal)" show-label>{{ __('club.matches.action.accept') }}</x-icon-btn>
                        <x-action-form :action="route('club.matches.proposals.dismiss', $proposal)" class="inline">
                            <x-icon-btn type="submit" icon="close" tone="ghost" size="xs" :label="__('club.matches.action.dismiss')" />
                        </x-action-form>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="upload_file" :colspan="8" :title="__('club.matches.empty.proposals')" :message="__('club.matches.hint.import')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$proposals" standing />
</x-index-page>
@endsection
