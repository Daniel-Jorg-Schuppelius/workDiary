{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Detailseite Mitglied (Feature 159, MVP-842): Stammdaten, Mitgliedschafts-
  verlauf, Gruppenzuordnungen, Vertretungen und offene Wechselvorschläge;
  Aktionen als Dialoge. Sichtbar für Register, eigene Gruppenleitung,
  das Mitglied selbst und seine Vertretung (Policy).
--}}
@extends('layouts.app')
@section('title', $member->fullName())
@section('nav-title', $member->fullName())
@section('content')
@php
    $hasLeft = $member->hasLeftOn($today);
    $age = $member->ageOn($today);
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('club.subtitle.member_show', ['no' => $member->displayNo(), 'joined' => $member->joined_on->format('d.m.Y')])"
                        :badge="$hasLeft ? __('club.label.left') : $member->kind->label()"
                        :badgeTone="$hasLeft ? 'ghost' : $member->kind->tone()">
            <x-slot:actions>
                @if ($canManage)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('club.members.edit', $member)"
                                show-label>{{ __('club.action.edit') }}</x-icon-btn>
                    @unless ($hasLeft)
                        <x-icon-btn icon="how_to_reg" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('club.members.kind.edit', $member)"
                                    show-label>{{ __('club.action.change_kind') }}</x-icon-btn>
                        <x-icon-btn icon="logout" tone="outline" size="sm" class="btn-warning"
                                    data-entry-modal-trigger
                                    :href="route('club.members.leave.edit', $member)"
                                    show-label>{{ __('club.action.leave') }}</x-icon-btn>
                    @endunless
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('club.members.index')"
                            show-label>{{ __('club.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.card.master_data')" icon="badge">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('club.field.member_no')" :value="$member->displayNo()" />
                    <x-detail-grid.row :label="__('club.field.name')" :value="$member->fullName()" />
                    <x-detail-grid.row :label="__('club.field.birth_date')">
                        @if ($member->birth_date)
                            {{ $member->birth_date->format('d.m.Y') }}
                            <span class="text-muted">· {{ trans_choice('club.label.age_years', $age ?? 0, ['age' => $age ?? 0]) }}</span>
                        @else
                            <span class="text-muted">{{ __('club.label.without_birth_date') }}</span>
                        @endif
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('club.field.email')" :value="$member->email ?? '–'" />
                    <x-detail-grid.row :label="__('club.field.phone')" :value="$member->phone ?? '–'" />
                    <x-detail-grid.row :label="__('club.field.address')" :value="trim(($member->street ?? '') . ', ' . trim(($member->postal_code ?? '') . ' ' . ($member->city ?? '')), ', ') ?: '–'" />
                    <x-detail-grid.row :label="__('club.field.kind')">
                        <x-status-badge :tone="$member->kind->tone()" size="sm">{{ $member->kind->label() }}</x-status-badge>
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('club.field.joined_on')" :value="$member->joined_on->format('d.m.Y')" />
                    @if ($member->left_on)
                        <x-detail-grid.row :label="__('club.field.left_on')" :value="$member->left_on->format('d.m.Y')" />
                    @endif
                    <x-detail-grid.row :label="__('club.field.user')" :value="$member->user?->name ?? __('club.label.no_account')" />
                    @if ($member->notes)
                        <x-detail-grid.row :label="__('club.field.notes')" :value="$member->notes" />
                    @endif
                </x-detail-grid>
            </x-card>

            <x-card :title="__('club.card.groups')" icon="groups" :count="$member->groupMemberships->count()">
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.group') }}</th>
                            <th>{{ __('club.field.status') }}</th>
                            <th>{{ __('club.field.valid_from') }}</th>
                            <th>{{ __('club.field.valid_to') }}</th>
                            <th>{{ __('club.field.note') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($member->groupMemberships as $membership)
                        <tr>
                            <td>
                                @if ($membership->group)
                                    <a href="{{ route('club.groups.show', $membership->group) }}" class="link link-hover">{{ $membership->group->name }}</a>
                                @else
                                    –
                                @endif
                            </td>
                            <td><x-status-badge :tone="$membership->status->tone()" size="sm">{{ $membership->status->label() }}</x-status-badge></td>
                            <td class="text-sm">{{ $membership->valid_from->format('d.m.Y') }}</td>
                            <td class="text-sm">{{ $membership->valid_to?->format('d.m.Y') ?? __('club.label.open_end') }}</td>
                            <td class="text-sm text-base-content/70">{{ $membership->note ?? '–' }}</td>
                        </tr>
                    @empty
                        <x-table.empty icon="groups" :colspan="5" :title="__('club.empty.member_groups')" compact />
                    @endforelse
                </x-table>
            </x-card>

            @if ($member->proposals->isNotEmpty())
                <x-card :title="__('club.card.proposals')" icon="swap_horiz" :count="$member->proposals->count()">
                    <ul class="space-y-1 text-sm">
                        @foreach ($member->proposals as $proposal)
                            <li class="flex flex-wrap items-center gap-2">
                                <x-status-badge :tone="$proposal->reason->tone()" size="sm">{{ $proposal->reason->label() }}</x-status-badge>
                                <span>{{ $proposal->group?->name }}</span>
                                @if ($proposal->suggestedGroup)
                                    <x-icon name="arrow_forward" class="text-muted" />
                                    <span>{{ $proposal->suggestedGroup->name }}</span>
                                @endif
                                <a href="{{ route('club.proposals.index') }}" class="link link-primary ml-auto">{{ __('club.action.decide') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.card.guardians')" icon="family_restroom" :count="$member->guardians->count()">
                @if ($canManage && ! $hasLeft)
                    <div class="mb-3">
                        <x-icon-btn icon="person_add" tone="outline" size="sm"
                                    data-entry-modal-trigger
                                    :href="route('club.members.guardians.create', $member)"
                                    show-label>{{ __('club.action.add_guardian') }}</x-icon-btn>
                    </div>
                @endif
                @forelse ($member->guardians as $guardian)
                    <div class="mb-3 rounded-box border border-base-300 p-3 text-sm {{ $guardian->isRevoked() ? 'opacity-60' : '' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $guardian->name }}</span>
                            @if ($guardian->isRevoked())
                                <x-status-badge tone="ghost" size="xs" :label="__('club.label.revoked')" />
                            @elseif (! $guardian->isActiveOn($today))
                                <x-status-badge tone="warning" size="xs" :label="__('club.label.inactive')" />
                            @endif
                            @if ($guardian->user)
                                <x-status-badge tone="info" size="xs" :label="__('club.label.linked')" />
                            @endif
                        </div>
                        <div class="mt-1 text-base-content/70">{{ implode(' · ', array_filter([$guardian->email, $guardian->phone])) ?: '–' }}</div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($guardian->permissionEnums() as $permission)
                                <span class="badge badge-ghost badge-xs">{{ $permission->label() }}</span>
                            @endforeach
                        </div>
                        @if ($guardian->valid_from || $guardian->valid_to)
                            <div class="mt-1 text-xs text-muted">{{ $guardian->valid_from?->format('d.m.Y') ?? '…' }} – {{ $guardian->valid_to?->format('d.m.Y') ?? __('club.label.open_end') }}</div>
                        @endif
                        @if ($canManage && ! $guardian->isRevoked())
                            <div class="mt-2 flex gap-1">
                                <x-icon-btn icon="edit" tone="outline" size="xs"
                                            data-entry-modal-trigger
                                            :href="route('club.members.guardians.edit', [$member, $guardian])"
                                            :label="__('club.action.edit')" />
                                <x-action-form :action="route('club.members.guardians.revoke', [$member, $guardian])" :confirm="__('club.confirm.revoke_guardian')" confirm-icon="person_off" confirm-tone="warning">
                                    <x-icon-btn type="submit" icon="person_off" tone="outline" size="xs" class="btn-warning" :label="__('club.action.revoke')" />
                                </x-action-form>
                            </div>
                        @endif
                    </div>
                @empty
                    <x-empty-state icon="family_restroom" :title="__('club.empty.guardians')" compact />
                @endforelse
            </x-card>

            <x-card :title="__('club.card.history')" icon="history" :count="$member->periods->count()">
                <ul class="space-y-1 text-sm">
                    @foreach ($member->periods->sortByDesc('starts_on') as $period)
                        <li class="flex flex-wrap items-center gap-2">
                            <x-status-badge :tone="$period->kind->tone()" size="xs">{{ $period->kind->label() }}</x-status-badge>
                            <span>{{ $period->starts_on->format('d.m.Y') }} – {{ $period->ends_on?->format('d.m.Y') ?? __('club.label.open_end') }}</span>
                            @if ($period->note)
                                <span class="text-muted">· {{ $period->note }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>

            @if ($canManage && $member->groupMemberships->isEmpty() && $member->guardians->isEmpty())
                <x-action-form :action="route('club.members.destroy', $member)" method="DELETE" :confirm="__('club.confirm.delete_member')" confirm-icon="delete" confirm-tone="error">
                    <x-icon-btn type="submit" icon="delete" tone="error" size="sm" show-label>{{ __('club.action.delete') }}</x-icon-btn>
                </x-action-form>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
