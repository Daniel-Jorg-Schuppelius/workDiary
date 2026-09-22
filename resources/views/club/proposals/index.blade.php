{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Wechsel-/Prüfvorschläge (Feature 159, MVP-842): Liste aus dem täglichen
  Kriterienabgleich, Entscheidung als Dialog; Filter Stand.
--}}
@extends('layouts.app')
@section('title', __('club.title.proposals'))
@section('nav-title', __('club.title.proposals'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('content')
<x-index-page overflow="clip" :subtitle="__('club.subtitle.proposals')">
    <x-slot:actions>
        @if ($canRefresh)
            <x-action-form :action="route('club.groups.refresh-proposals')">
                <x-icon-btn type="submit" icon="refresh" tone="outline" size="sm" show-label>{{ __('club.action.refresh_proposals') }}</x-icon-btn>
            </x-action-form>
        @endif
    </x-slot:actions>

    <x-filter-bar :action="route('club.proposals.index')" :reset="route('club.proposals.index')">
        <x-filter-field :label="__('club.field.status')" for="flt-status">
            <select id="flt-status" name="status" class="select select-sm select-bordered w-44 shrink-0" data-autosubmit>
                <option value="" @selected($status === '')>{{ __('club.filter.all_statuses') }}</option>
                @foreach (\App\Enums\Club\ClubProposalStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-filter-field>
    </x-filter-bar>

    <x-table scroll="flex">
        <x-slot:head>
            <tr>
                <th>{{ __('club.field.member') }}</th>
                <th>{{ __('club.field.group') }}</th>
                <th>{{ __('club.field.reason') }}</th>
                <th>{{ __('club.field.suggested_group') }}</th>
                <th>{{ __('club.field.since') }}</th>
                <th>{{ __('club.field.status') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($proposals as $proposal)
            <tr class="hover">
                <td class="font-medium">
                    @if ($proposal->member)
                        <a href="{{ route('club.members.show', $proposal->member) }}" class="link link-hover">{{ $proposal->member->fullName() }}</a>
                        <span class="font-mono text-xs text-muted">{{ $proposal->member->displayNo() }}</span>
                    @endif
                </td>
                <td class="text-sm">
                    @if ($proposal->group)
                        <a href="{{ route('club.groups.show', $proposal->group) }}" class="link link-hover">{{ $proposal->group->name }}</a>
                    @endif
                </td>
                <td><x-status-badge :tone="$proposal->reason->tone()" size="sm">{{ $proposal->reason->label() }}</x-status-badge></td>
                <td class="text-sm">{{ $proposal->suggestedGroup?->name ?? '–' }}</td>
                <td class="text-sm text-base-content/70">{{ $proposal->created_at?->orgTz()->format('d.m.Y') }}</td>
                <td>
                    <x-status-badge :tone="$proposal->status->tone()" size="sm">{{ $proposal->status->label() }}</x-status-badge>
                    @if ($proposal->effective_on)
                        <span class="text-xs text-muted">{{ $proposal->effective_on->format('d.m.Y') }}</span>
                    @endif
                </td>
                <td class="text-right">
                    @if ($proposal->isOpen() && \Illuminate\Support\Facades\Gate::allows('decide', $proposal))
                        <div class="flex justify-end gap-1">
                            <x-icon-btn icon="gavel" tone="outline" size="xs"
                                        data-entry-modal-trigger
                                        :href="route('club.proposals.decide.edit', $proposal)"
                                        :label="__('club.action.decide')" />
                            <x-action-form :action="route('club.proposals.dismiss', $proposal)" :confirm="__('club.confirm.dismiss_proposal')" confirm-icon="close" confirm-tone="warning">
                                <x-icon-btn type="submit" icon="close" tone="outline" size="xs" class="btn-warning" :label="__('club.action.dismiss')" />
                            </x-action-form>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <x-table.empty icon="swap_horiz" :colspan="7" :title="__('club.empty.proposals')" compact />
        @endforelse
    </x-table>
    <x-pagination :paginator="$proposals" standing />
</x-index-page>
@endsection
