{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Detailseite Gruppe (Feature 159, MVP-842): Stammdaten und Kriterien,
  aktive Mitglieder, offene Anträge, offene Wechselvorschläge; Aufnahme,
  Freigabe und Beenden als Dialoge. Leitung sieht nur eigene Gruppen.
--}}
@extends('layouts.app')
@section('title', $group->name)
@section('nav-title', $group->name)
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('club.subtitle.group_show', ['name' => $group->name]) . ($group->department ? ' · ' . $group->department->name : '')"
                        :badge="$group->is_active ? $group->admission_mode->label() : __('club.label.inactive')"
                        :badgeTone="$group->is_active ? 'primary' : 'ghost'">
            <x-slot:actions>
                @if ($canDecide && $group->is_active)
                    <x-icon-btn icon="person_add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('club.groups.admit.create', $group)"
                                show-label>{{ __('club.action.admit') }}</x-icon-btn>
                @endif
                @if ($canManage)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('club.groups.edit', $group)"
                                show-label>{{ __('club.action.edit') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('club.groups.index')"
                            show-label>{{ __('club.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.card.active_members')" icon="groups" :count="$active->count()">
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th class="text-center">{{ __('club.field.age') }}</th>
                            <th>{{ __('club.field.valid_from') }}</th>
                            <th>{{ __('club.label.criteria') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($active as $membership)
                        @php
                            $member = $membership->member;
                            $result = $member ? app(\App\Services\Club\ClubGroupService::class)->evaluate($group, $member, $today) : null;
                        @endphp
                        <tr>
                            <td class="font-medium">
                                @if ($member)
                                    <a href="{{ route('club.members.show', $member) }}" class="link link-hover">{{ $member->fullName() }}</a>
                                    <span class="font-mono text-xs text-muted">{{ $member->displayNo() }}</span>
                                @endif
                            </td>
                            <td class="text-center text-sm">{{ $member?->ageOn($today) ?? '–' }}</td>
                            <td class="text-sm">{{ $membership->valid_from->format('d.m.Y') }}</td>
                            <td>
                                @if ($result !== null && $group->hasAgeCriteria())
                                    <x-status-badge :tone="$result->tone()" size="xs">{{ $result->label() }}</x-status-badge>
                                @else
                                    <span class="text-muted">–</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($canDecide)
                                    <x-icon-btn icon="person_remove" tone="outline" size="xs" class="btn-warning"
                                                data-entry-modal-trigger
                                                :href="route('club.groups.memberships.end.edit', [$group, $membership])"
                                                :label="__('club.action.end')" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="groups" :colspan="5" :title="__('club.empty.group_members')" compact />
                    @endforelse
                </x-table>
                @if ($endedCount > 0)
                    <p class="mt-2 text-xs text-muted">{{ __('club.label.ended_count', ['count' => $endedCount]) }}</p>
                @endif
            </x-card>

            @if ($group->is_team)
                {{-- Saisonkader (MVP-852): Kader je Saison, Gastspieler mit Herkunft; Vorsaisons bleiben erhalten. --}}
                <x-card :title="__('club.teams.card.squad') . ($season ? ' · ' . $season->name : '')" icon="group_add" :count="$squadMembers->count()">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        @if ($seasons->isNotEmpty())
                            <form method="GET" action="{{ route('club.groups.show', $group) }}" class="flex items-center gap-2">
                                <label for="squad-season" class="text-xs text-muted">{{ __('club.teams.field.season') }}</label>
                                <select id="squad-season" name="season" class="select select-bordered select-xs" data-autosubmit>
                                    @foreach ($seasons as $option)
                                        <option value="{{ $option->sqid }}" @selected($season?->id === $option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endif
                        @if ($canManage)
                            <x-icon-btn icon="calendar_month" tone="ghost" size="xs" data-entry-modal-trigger :href="route('club.seasons.create')" show-label>{{ __('club.teams.action.create_season') }}</x-icon-btn>
                        @endif
                        @if ($canDecide && $season)
                            <x-icon-btn icon="group_add" tone="primary" size="xs" data-entry-modal-trigger :href="route('club.groups.squad.create', [$group, 'season' => $season->sqid])" show-label>{{ __('club.teams.action.add_squad_member') }}</x-icon-btn>
                        @endif
                        <x-icon-btn icon="sports_soccer" tone="outline" size="xs" class="ml-auto" :href="route('club.matches.index', ['team' => $group->sqid])" show-label>{{ __('club.matches.title.index') }}</x-icon-btn>
                        @if ($canDecide)
                            <x-icon-btn icon="upload_file" tone="outline" size="xs" data-entry-modal-trigger :href="route('club.matches.proposals.import.create', ['team' => $group->sqid])" show-label>{{ __('club.matches.action.import') }}</x-icon-btn>
                        @endif
                    </div>
                    @if ($profile)
                        <p class="mb-2 text-xs text-muted">{{ __('club.teams.label.profile_summary', ['name' => $profile->name, 'family' => $profile->family->label(), 'field' => $profile->squad_size_field ?? '–', 'bench' => $profile->squad_size_bench ?? '–']) }}</p>
                    @else
                        <p class="mb-2 text-xs text-warning">{{ __('club.teams.hint.no_profile') }}</p>
                    @endif
                    @if ($season === null)
                        <p class="text-sm text-muted">{{ __('club.teams.empty.seasons') }}</p>
                    @else
                        <x-table :bare="true" size="sm">
                            <x-slot:head>
                                <tr>
                                    <th>{{ __('club.field.member') }}</th>
                                    <th class="text-center">{{ __('club.teams.field.jersey_no') }}</th>
                                    <th>{{ __('club.teams.field.position') }}</th>
                                    @if ($profile?->hasPairings())<th class="text-center">{{ __('club.teams.field.strength_rank') }}</th>@endif
                                    <th>{{ __('club.field.valid_from') }}</th>
                                    <th></th>
                                </tr>
                            </x-slot:head>
                            @forelse ($squadMembers as $entry)
                                @php($ended = $entry->valid_to !== null && $entry->valid_to->lt($today))
                                <tr class="{{ $ended ? 'opacity-60' : '' }}">
                                    <td class="text-sm">
                                        @if ($entry->member)
                                            <a href="{{ route('club.members.show', $entry->member) }}" class="link link-hover font-medium">{{ $entry->member->fullName() }}</a>
                                            <span class="block text-xs text-muted">{{ __('club.teams.label.age_class_of', ['age' => app(\App\Services\Club\ClubTeamService::class)->ageClassOf($entry->member, $group, $today) ?? '–']) }}</span>
                                        @endif
                                        @if ($entry->isGuest())<span class="badge badge-ghost badge-xs">{{ __('club.teams.label.guest', ['origin' => $entry->guest_origin]) }}</span>@endif
                                    </td>
                                    <td class="text-center text-sm tabular-nums">{{ $entry->jersey_no ?? '–' }}</td>
                                    <td class="text-sm">{{ $profile?->positionLabel($entry->position_code) ?? $entry->position_code ?? '–' }}</td>
                                    @if ($profile?->hasPairings())<td class="text-center text-sm tabular-nums">{{ $entry->strength_rank ?? '–' }}</td>@endif
                                    <td class="text-sm tabular-nums">{{ $entry->valid_from->format('d.m.Y') }}@if ($entry->valid_to) – {{ $entry->valid_to->format('d.m.Y') }}@endif</td>
                                    <td class="text-right">
                                        @if ($canDecide)
                                            <x-icon-btn icon="edit" tone="ghost" size="xs" data-entry-modal-trigger :href="route('club.groups.squad.edit', [$group, $entry])" :label="__('club.action.edit')" />
                                            @unless ($ended)
                                                <x-action-form :action="route('club.groups.squad.end', [$group, $entry])" :confirm="__('club.teams.confirm.end_squad_member', ['name' => $entry->member?->fullName() ?? ''])" confirm-icon="person_remove" confirm-tone="warning" class="inline">
                                                    <x-icon-btn type="submit" icon="person_remove" tone="ghost" size="xs" class="btn-warning" :label="__('club.action.end')" />
                                                </x-action-form>
                                            @endunless
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <x-table.empty icon="group_add" :colspan="$profile?->hasPairings() ? 6 : 5" :title="__('club.teams.empty.squad')" compact />
                            @endforelse
                        </x-table>
                    @endif
                </x-card>
            @endif
            <x-card :title="__('club.card.requests')" icon="pending_actions" :count="$requested->count()">
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.field.valid_from') }}</th>
                            <th>{{ __('club.field.note') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($requested as $membership)
                        <tr>
                            <td class="font-medium">
                                @if ($membership->member)
                                    <a href="{{ route('club.members.show', $membership->member) }}" class="link link-hover">{{ $membership->member->fullName() }}</a>
                                @endif
                            </td>
                            <td class="text-sm">{{ $membership->valid_from->format('d.m.Y') }}</td>
                            <td class="text-sm text-base-content/70">{{ $membership->note ?? '–' }}</td>
                            <td class="text-right">
                                @if ($canDecide)
                                    <div class="flex justify-end gap-1">
                                        <x-icon-btn icon="check" tone="outline" size="xs" class="btn-success"
                                                    data-entry-modal-trigger
                                                    :href="route('club.groups.memberships.approve.edit', [$group, $membership])"
                                                    :label="__('club.action.approve')" />
                                        <x-action-form :action="route('club.groups.memberships.reject', [$group, $membership])" :confirm="__('club.confirm.reject_request')" confirm-icon="close" confirm-tone="error">
                                            <x-icon-btn type="submit" icon="close" tone="outline" size="xs" class="btn-error" :label="__('club.action.reject')" />
                                        </x-action-form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="pending_actions" :colspan="4" :title="__('club.empty.requests')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.card.criteria')" icon="rule">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('club.field.leader')" :value="$group->leader?->name ?? '–'" />
                    <x-detail-grid.row :label="__('club.field.admission_mode')" :value="$group->admission_mode->label()" />
                    <x-detail-grid.row :label="__('club.field.age_range')" :value="$group->ageRangeLabel() ?? '–'" />
                    <x-detail-grid.row :label="__('club.field.max_members')" :value="$group->max_members !== null ? $active->count() . ' / ' . $group->max_members : __('club.label.unlimited')" />
                    @if ($group->criteria_note)
                        <x-detail-grid.row :label="__('club.field.criteria_note')" :value="$group->criteria_note" />
                    @endif
                    @if ($group->description)
                        <x-detail-grid.row :label="__('club.field.description')" :value="$group->description" />
                    @endif
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('club.hint.criteria') }}</p>
            </x-card>

            <x-card :title="__('club.card.proposals')" icon="swap_horiz" :count="$proposals->count()">
                @forelse ($proposals as $proposal)
                    <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                        <x-status-badge :tone="$proposal->reason->tone()" size="xs">{{ $proposal->reason->label() }}</x-status-badge>
                        @if ($proposal->member)
                            <a href="{{ route('club.members.show', $proposal->member) }}" class="link link-hover">{{ $proposal->member->fullName() }}</a>
                        @endif
                        @if ($proposal->suggestedGroup)
                            <x-icon name="arrow_forward" class="text-muted" />
                            <span>{{ $proposal->suggestedGroup->name }}</span>
                        @endif
                        @if ($canDecide)
                            <x-icon-btn icon="gavel" tone="outline" size="xs" class="ml-auto"
                                        data-entry-modal-trigger
                                        :href="route('club.proposals.decide.edit', $proposal)"
                                        :label="__('club.action.decide')" />
                        @endif
                    </div>
                @empty
                    <x-empty-state icon="swap_horiz" :title="__('club.empty.proposals')" compact />
                @endforelse
                @if ($canDecide && $group->hasAgeCriteria())
                    <x-action-form :action="route('club.groups.refresh-proposals')" class="mt-3">
                        <x-icon-btn type="submit" icon="refresh" tone="outline" size="sm" show-label>{{ __('club.action.refresh_proposals') }}</x-icon-btn>
                    </x-action-form>
                @endif
            </x-card>

            @if ($canManage && $active->isEmpty() && $requested->isEmpty())
                <x-action-form :action="route('club.groups.destroy', $group)" method="DELETE" :confirm="__('club.confirm.delete_group')" confirm-icon="delete" confirm-tone="error">
                    <x-icon-btn type="submit" icon="delete" tone="error" size="sm" show-label>{{ __('club.action.delete') }}</x-icon-btn>
                </x-action-form>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
