{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Mitgliederregister (Feature 159, MVP-842): Voll-Höhe-Tabelle Nummer/Name/
  Art/Alter/Gruppen/Eintritt/Status; Filter Suche, Art, Status, Gruppe.
  Gruppenleitung ohne Registerrecht sieht nur Mitglieder ihrer Gruppen.
--}}
@extends('layouts.app')
@section('title', __('club.title.members'))
@section('nav-title', __('club.title.members'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.subtitle.members')">
    <x-slot:actions>
        @if ($canManage)
            <x-icon-btn icon="add" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('club.members.create')"
                        show-label>{{ __('club.action.create_member') }}</x-icon-btn>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('club.members.index')" :reset="route('club.members.index')">
        <input type="search" name="q" value="{{ $filters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('club.filter.search') }}" aria-label="{{ __('club.filter.search') }}" />
        <x-filter-field :label="__('club.field.kind')" for="flt-kind">
            <select id="flt-kind" name="kind" class="select select-sm select-bordered w-40 shrink-0" data-autosubmit>
                <option value="">{{ __('club.filter.all_kinds') }}</option>
                @foreach (\App\Enums\Club\ClubMembershipKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
        <x-filter-field :label="__('club.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered w-44 shrink-0" data-autosubmit>
                <option value="current" @selected($filters['status'] === 'current')>{{ __('club.filter.status_current') }}</option>
                <option value="left" @selected($filters['status'] === 'left')>{{ __('club.filter.status_left') }}</option>
                <option value="all" @selected($filters['status'] === 'all')>{{ __('club.filter.status_all') }}</option>
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
                <th>{{ __('club.field.member_no') }}</th>
                <th>{{ __('club.field.name') }}</th>
                <th>{{ __('club.field.kind') }}</th>
                <th class="text-center">{{ __('club.field.age') }}</th>
                <th>{{ __('club.field.groups') }}</th>
                <th>{{ __('club.field.joined_on') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($members as $member)
            @php($age = $member->ageOn($today))
            <tr class="hover">
                <td class="font-mono text-sm">{{ $member->displayNo() }}</td>
                <td class="font-medium">
                    <a href="{{ route('club.members.show', $member) }}" class="link link-hover">{{ $member->fullName() }}</a>
                    @if ($member->user_id !== null)
                        <x-status-badge tone="info" size="xs" :label="__('club.label.linked')" />
                    @endif
                </td>
                <td><x-status-badge :tone="$member->kind->tone()" size="sm">{{ $member->kind->label() }}</x-status-badge></td>
                <td class="text-center text-sm">{{ $age ?? '–' }}</td>
                <td class="text-sm">
                    @forelse ($member->activeGroupMemberships as $membership)
                        <span class="badge badge-ghost badge-sm">{{ $membership->group?->name }}</span>
                    @empty
                        <span class="text-muted">–</span>
                    @endforelse
                </td>
                <td class="text-sm text-base-content/70">{{ $member->joined_on->format('d.m.Y') }}</td>
                <td>
                    @if ($member->hasLeftOn($today))
                        <x-status-badge tone="ghost" size="sm">{{ __('club.label.left') }} · {{ $member->left_on?->format('d.m.Y') }}</x-status-badge>
                    @else
                        <x-status-badge tone="success" size="sm">{{ __('club.label.current') }}</x-status-badge>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex justify-end gap-1">
                        <x-icon-btn icon="visibility" :href="route('club.members.show', $member)" :label="__('club.action.show')" />
                        @if ($canManage)
                            <x-icon-btn icon="edit" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('club.members.edit', $member)"
                                        :label="__('club.action.edit')" />
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <x-table.empty icon="groups" :colspan="8" :title="__('club.empty.members')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$members" standing />
</x-index-page>
@endsection
