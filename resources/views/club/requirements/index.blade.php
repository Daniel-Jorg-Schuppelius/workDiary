{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Nachweisliste (MVP-855): vom Verein konfigurierte Anforderungen (Anzahl bestätigter Anwesenheiten je Zeitraum),
  Bericht je Mitglied zum Stichtag, CSV-Export. Variablen: $requirements, $selected, $asOf, $report, $canManage.
--}}
@extends('layouts.app')
@section('title', __('club.competitions.title.requirements'))
@section('nav-title', __('club.competitions.title.requirements'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.competitions.subtitle.requirements')">
    <x-slot:actions>
        @if ($selected)
            <x-icon-btn icon="download" tone="outline" size="sm" :href="route('club.requirements.export', [$selected, 'as_of' => $asOf->toDateString()])" show-label>{{ __('club.competitions.action.export') }}</x-icon-btn>
            @if ($canManage)
                <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.requirements.edit', $selected)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
            @endif
        @endif
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.requirements.create')" show-label>{{ __('club.competitions.action.create_requirement') }}</x-icon-btn>
        @endif
        <x-help-button topic="club.competitions" />
    </x-slot:actions>

    <x-filter-bar :action="route('club.requirements.index')" :reset="route('club.requirements.index')">
        <x-filter-field :label="__('club.competitions.field.requirement')" for="flt-requirement">
            <select id="flt-requirement" name="requirement" class="select select-sm select-bordered w-64 shrink-0" data-autosubmit>
                @foreach ($requirements as $requirement)
                    <option value="{{ $requirement->sqid }}" @selected($selected?->id === $requirement->id)>{{ $requirement->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('club.competitions.field.as_of')" for="flt-asof">
            <input id="flt-asof" type="date" name="as_of" value="{{ $asOf->toDateString() }}" class="input input-sm input-bordered w-40" data-autosubmit>
        </x-filter-field>
    </x-filter-bar>

    @if ($selected)
        <p class="mb-2 text-xs text-muted">{{ __('club.competitions.label.requirement_summary', ['required' => $selected->required_count, 'months' => $selected->period_months, 'scope' => $selected->group?->name ?? $selected->department?->name ?? __('club.competitions.label.all_members'), 'kind' => $selected->event_kind?->label() ?? __('club.competitions.label.all_kinds')]) }}</p>
    @endif

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.field.member') }}</th>
                <th class="text-center">{{ __('club.competitions.field.count') }}</th>
                <th class="text-center">{{ __('club.competitions.field.required') }}</th>
                <th>{{ __('club.competitions.field.met') }}</th>
                <th>{{ __('club.competitions.field.last_on') }}</th>
            </tr>
        </x-slot:head>
        @forelse ($report as $row)
            <tr class="hover">
                <td class="font-medium"><a href="{{ route('club.members.show', $row['member']) }}" class="link link-hover">{{ $row['member']->fullName() }}</a> <span class="font-mono text-xs text-muted">{{ $row['member']->displayNo() }}</span></td>
                <td class="text-center text-sm tabular-nums">{{ $row['count'] }}</td>
                <td class="text-center text-sm tabular-nums">{{ $row['required'] }}</td>
                <td><x-status-badge :tone="$row['met'] ? 'success' : 'warning'" size="xs">{{ $row['met'] ? __('club.competitions.label.met') : __('club.competitions.label.not_met') }}</x-status-badge></td>
                <td class="text-sm tabular-nums">{{ $row['last_on']?->format('d.m.Y') ?? '–' }}</td>
            </tr>
        @empty
            <x-table.empty icon="fact_check" :colspan="5" :title="$selected ? __('club.competitions.empty.report') : __('club.competitions.empty.requirements')" :message="__('club.competitions.hint.requirements')" compact />
        @endforelse
    </x-table>
</x-index-page>
@endsection
