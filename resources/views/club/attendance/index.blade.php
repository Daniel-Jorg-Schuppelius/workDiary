{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Nachweisliste (Feature 159, MVP-844): bestätigte Anwesenheitsnachweise im
  globalen Header-Zeitraum, Filter Suche/Gruppe/nur angerechnet, CSV-Export.
--}}
@extends('layouts.app')
@section('title', __('club.attendance.title.index'))
@section('nav-title', __('club.attendance.title.index'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.attendance.subtitle.index')">
    <x-slot:actions>
        <x-icon-btn icon="download" tone="outline" size="sm"
                    :href="route('club.attendance.export', request()->query())"
                    show-label>{{ __('club.attendance.action.export') }}</x-icon-btn>
    </x-slot:actions>

    <x-filter-bar :action="route('club.attendance.index')" :reset="route('club.attendance.index')">
        <input type="search" name="q" value="{{ $filters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('club.filter.search') }}" aria-label="{{ __('club.filter.search') }}" />
        <x-filter-field :label="__('club.field.group')" for="flt-group">
            <select id="flt-group" name="group" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="">{{ __('club.filter.all_groups') }}</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->sqid }}" @selected($filters['group'] === $group->sqid)>{{ $group->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-toggle name="only_credited" :label="__('club.attendance.filter.only_credited')" :checked="$filters['only_credited']" />
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.events.field.starts') }}</th>
                <th>{{ __('club.events.field.title') }}</th>
                <th>{{ __('club.field.member') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th class="text-right">{{ __('club.attendance.field.minutes') }}</th>
                <th class="text-right">{{ __('club.attendance.field.credited') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($records as $record)
            <tr class="hover">
                <td class="whitespace-nowrap text-sm tabular-nums">{{ $record->event?->started_at?->orgTz()->format('d.m.Y H:i') }}</td>
                <td class="text-sm">
                    @if ($record->event)
                        <a href="{{ route('club.events.attendance.show', $record->event) }}" class="link link-hover">{{ $record->event->title }}</a>
                    @endif
                </td>
                <td class="font-medium">
                    @if ($record->member)
                        <a href="{{ route('club.members.show', $record->member) }}" class="link link-hover">{{ $record->member->fullName() }}</a>
                        <span class="font-mono text-xs text-muted">{{ $record->member->displayNo() }}</span>
                    @endif
                </td>
                <td>
                    <x-status-badge :tone="$record->status->tone()" size="sm" :icon="$record->status->icon()">{{ $record->status->label() }}</x-status-badge>
                    @if ($record->hasUnresolvedOverlap())
                        <x-status-badge tone="warning" size="xs" :label="__('club.attendance.label.overlap')" />
                    @endif
                </td>
                <td class="text-right tabular-nums">{{ $record->minutes ?? '–' }}</td>
                <td class="text-right tabular-nums">{{ $record->sheet ? $record->creditableMinutes($record->sheet) : 0 }}</td>
                <td></td>
            </tr>
        @empty
            <x-table.empty icon="fact_check" :colspan="7" :title="__('club.attendance.empty.index')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$records" standing />
</x-index-page>
@endsection
