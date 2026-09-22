{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Vereinstermine (Feature 159, MVP-843): Voll-Höhe-Liste der Kalender-Events
  mit Vereinsdetails — Zeit/Titel/Art/Zielgruppen/Leitung/Ort/Belegung/Status.
  Filter: Zeitraum (anstehend, Kopfzeile, vergangen), Art, Gruppe.
--}}
@extends('layouts.app')
@section('title', __('club.events.title.index'))
@section('nav-title', __('club.events.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.events.subtitle.index')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('club.events.create')"
                        show-label>{{ __('club.events.action.create') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('club.events.index')" :reset="route('club.events.index')">
        <x-filter-field :label="__('club.events.field.period')" for="flt-period">
            <select id="flt-period" name="period" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="upcoming" @selected($filters['period'] === 'upcoming')>{{ __('club.events.filter.period_upcoming') }}</option>
                <option value="range" @selected($filters['period'] === 'range')>{{ __('club.events.filter.period_range') }}</option>
                <option value="past" @selected($filters['period'] === 'past')>{{ __('club.events.filter.period_past') }}</option>
            </select>
        </x-filter-field>
        <x-filter-field :label="__('club.events.field.kind')" for="flt-kind">
            <select id="flt-kind" name="kind" class="select select-sm select-bordered w-40 shrink-0" data-autosubmit>
                <option value="">{{ __('club.events.filter.all_kinds') }}</option>
                @foreach (\App\Enums\Club\ClubEventKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('club.field.group')" for="flt-group">
            <select id="flt-group" name="group" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="">{{ __('club.filter.all_groups') }}</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->sqid }}" @selected($filters['group'] === $group->sqid)>{{ $group->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.events.field.starts') }}</th>
                <th>{{ __('club.events.field.title') }}</th>
                <th>{{ __('club.events.field.groups') }}</th>
                <th>{{ __('club.events.field.leader') }}</th>
                <th>{{ __('club.events.field.room') }}</th>
                <th class="text-center">{{ __('club.events.field.occupancy') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($events as $event)
            @php($details = $event->clubDetails)
            <tr class="hover {{ $event->cancelled_at ? 'opacity-60' : '' }}">
                <td class="whitespace-nowrap text-sm tabular-nums">
                    {{ $event->started_at->orgTz()->format('d.m.Y H:i') }}–{{ $event->ended_at->orgTz()->format('H:i') }}
                    @if ($event->series_id !== null || $event->recurrence_rule)
                        <x-icon name="repeat" class="text-muted" />
                    @endif
                </td>
                <td class="font-medium">
                    <a href="{{ route('club.events.show', $event) }}" class="link link-hover">{{ $event->title }}</a>
                    @if ($details)
                        <span class="badge badge-ghost badge-xs">{{ $details->kind->label() }}</span>
                    @endif
                </td>
                <td class="text-sm">
                    @if ($details?->visibility === \App\Enums\Club\ClubEventVisibility::Club)
                        <span class="text-muted">{{ __('club.events.label.all_members') }}</span>
                    @else
                        @forelse ($event->clubGroups as $group)
                            <span class="badge badge-ghost badge-sm">{{ $group->name }}</span>
                        @empty
                            <span class="text-muted">–</span>
                        @endforelse
                    @endif
                </td>
                <td class="text-sm">{{ $event->responsibleUser?->name ?? '–' }}</td>
                <td class="text-sm">{{ $event->rooms->pluck('name')->implode(', ') ?: '–' }}</td>
                <td class="text-center text-sm tabular-nums">
                    {{ $event->registered_count }}@if ($event->max_participants !== null) / {{ $event->max_participants }}@endif
                    @if ($event->waitlisted_count > 0)
                        <span class="badge badge-warning badge-xs">+{{ $event->waitlisted_count }}</span>
                    @endif
                </td>
                <td><x-status-badge :tone="$event->status->tone()" size="sm">{{ $event->status->label() }}</x-status-badge></td>
                <td class="text-right">
                    <x-icon-btn icon="visibility" :href="route('club.events.show', $event)" :label="__('club.action.show')" />
                </td>
            </tr>
        @empty
            <x-table.empty icon="event" :colspan="8" :title="__('club.events.empty.index')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$events" standing />
</x-index-page>
@endsection
