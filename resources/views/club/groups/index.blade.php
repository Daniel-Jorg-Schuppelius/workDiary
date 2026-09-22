{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Gruppenregister (Feature 159, MVP-842): Name/Abteilung/Leitung/Alter/
  Belegung/Aufnahme/Status; Filter Suche, Abteilung, nur aktive.
--}}
@extends('layouts.app')
@section('title', __('club.title.groups'))
@section('nav-title', __('club.title.groups'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.subtitle.groups')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('club.groups.create')"
                        show-label>{{ __('club.action.create_group') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('club.groups.index')" :reset="route('club.groups.index')">
        <input type="search" name="q" value="{{ $filters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />
        <x-filter-field :label="__('club.field.department')" for="flt-department">
            <select id="flt-department" name="department" class="select select-sm select-bordered w-48 shrink-0" data-autosubmit>
                <option value="">{{ __('club.filter.all_departments') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->sqid }}" @selected($filters['department'] === $department->sqid)>{{ $department->name }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-toggle name="active" :label="__('club.filter.active_only')" :checked="$filters['active']" />
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.field.name') }}</th>
                <th>{{ __('club.field.department') }}</th>
                <th>{{ __('club.field.leader') }}</th>
                <th>{{ __('club.field.age_range') }}</th>
                <th class="text-center">{{ __('club.field.members_count') }}</th>
                <th class="text-center">{{ __('club.field.requests') }}</th>
                <th class="text-center">{{ __('club.field.proposals') }}</th>
                <th>{{ __('club.field.admission_mode') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($groups as $group)
            <tr class="hover {{ $group->is_active ? '' : 'opacity-60' }}">
                <td class="font-medium">
                    <a href="{{ route('club.groups.show', $group) }}" class="link link-hover">{{ $group->name }}</a>
                    @unless ($group->is_active)
                        <x-status-badge tone="ghost" size="xs" :label="__('club.label.inactive')" />
                    @endunless
                </td>
                <td class="text-sm">{{ $group->department?->name ?? __('club.label.no_department') }}</td>
                <td class="text-sm">{{ $group->leader?->name ?? '–' }}</td>
                <td class="text-sm">{{ $group->ageRangeLabel() ?? '–' }}</td>
                <td class="text-center text-sm tabular-nums">{{ $group->active_memberships_count }}@if ($group->max_members !== null) / {{ $group->max_members }}@endif</td>
                <td class="text-center text-sm">
                    @if ($group->requested_memberships_count > 0)
                        <span class="badge badge-warning badge-sm">{{ $group->requested_memberships_count }}</span>
                    @else
                        <span class="text-muted">–</span>
                    @endif
                </td>
                <td class="text-center text-sm">
                    @if ($group->open_proposals_count > 0)
                        <span class="badge badge-warning badge-sm">{{ $group->open_proposals_count }}</span>
                    @else
                        <span class="text-muted">–</span>
                    @endif
                </td>
                <td class="text-sm">{{ $group->admission_mode->label() }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" :href="route('club.groups.show', $group)" :label="__('club.action.show')" />
                        @if ($canManage)
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('club.groups.edit', $group)"
                                        :label="__('club.action.edit')" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="diversity_3" :colspan="9" :title="__('club.empty.groups')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$groups" standing />
</x-index-page>
@endsection
