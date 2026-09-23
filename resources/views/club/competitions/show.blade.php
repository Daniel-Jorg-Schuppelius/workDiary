{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Detailseite Wettkampf (MVP-855): Meldungen je Disziplin mit Klärungsstatus, Ergebnisse, Details, Pflichtrolle Standaufsicht.
  Variablen: $event, $competition, $details, $entries, $performances, $isCancelled, $canManage, $canEdit, $canConfirm,
  $missingRangeOfficer, $reviewCount.
--}}
@extends('layouts.app')
@section('title', $event->title)
@section('nav-title', $event->title)
@section('content')
@php
    $start = $event->started_at->orgTz();
    $end = $event->ended_at->orgTz();
    $timeLabel = $start->format('d.m.Y H:i') . ' – ' . ($start->isSameDay($end) ? $end->format('H:i') : $end->format('d.m.Y H:i'));
    $byDiscipline = $entries->groupBy('discipline_code');
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$timeLabel . ' · ' . ($competition->profile?->name ?? '') . ($competition->venue ? ' · ' . $competition->venue : '')"
                        :badge="$isCancelled ? __('club.events.label.cancelled') : ($reviewCount > 0 ? __('club.competitions.label.review_count', ['count' => $reviewCount]) : $details->kind->label())"
                        :badgeTone="$isCancelled ? 'error' : ($reviewCount > 0 ? 'warning' : 'primary')">
            <x-slot:actions>
                @if ($canManage && ! $isCancelled)
                    <x-icon-btn icon="how_to_reg" tone="primary" size="sm" data-entry-modal-trigger :href="route('club.competitions.entries.create', $event)" show-label>{{ __('club.competitions.action.enter') }}</x-icon-btn>
                    <x-icon-btn icon="sports" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.matches.roles.create', $event)" show-label>{{ __('club.matches.action.add_role') }}</x-icon-btn>
                    <x-icon-btn icon="fact_check" tone="outline" size="sm" :href="route('club.events.attendance.show', $event)" show-label>{{ __('club.attendance.action.open') }}</x-icon-btn>
                @endif
                @if ($canEdit)
                    <x-icon-btn icon="edit" tone="outline" size="sm" data-entry-modal-trigger :href="route('club.competitions.edit', $event)" show-label>{{ __('club.action.edit') }}</x-icon-btn>
                    @unless ($isCancelled)
                        <x-icon-btn icon="event_busy" tone="outline" size="sm" class="btn-warning" data-entry-modal-trigger :href="route('club.events.cancel.edit', $event)" show-label>{{ __('club.events.action.cancel_event') }}</x-icon-btn>
                    @endunless
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('club.competitions.index')" show-label>{{ __('club.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($missingRangeOfficer)
        <div class="alert alert-warning mb-3 text-sm" role="status"><x-icon name="security" /><span>{{ __('club.competitions.hint.range_officer_missing') }}</span></div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.competitions.card.entries')" icon="how_to_reg" :count="$entries->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.competitions.hint.entries') }}</p>
                @forelse ($competition->disciplines as $code)
                    @php($discipline = $competition->discipline($code))
                    <h4 class="mt-2 text-sm font-semibold">{{ $discipline['label'] ?? $code }}@if ($discipline['unit'] ?? null) <span class="text-xs font-normal text-muted">({{ $discipline['unit'] }})</span>@endif</h4>
                    <x-table :bare="true" size="sm">
                        <x-slot:head>
                            <tr><th>{{ __('club.field.member') }}</th><th>{{ __('club.field.status') }}</th><th>{{ __('club.competitions.field.result') }}</th><th></th></tr>
                        </x-slot:head>
                        @forelse ($byDiscipline->get($code, collect()) as $entry)
                            @php($result = $performances->first(fn ($p) => $p->club_member_id === $entry->club_member_id && $p->discipline_code === $code))
                            <tr class="{{ $entry->status === \App\Enums\Club\ClubEntryStatus::Withdrawn ? 'opacity-60' : '' }}">
                                <td class="text-sm">@if ($entry->member)<a href="{{ route('club.members.show', $entry->member) }}" class="link link-hover">{{ $entry->member->fullName() }}</a>@endif</td>
                                <td>
                                    <x-status-badge :tone="$entry->status->tone()" size="xs">{{ $entry->status->label() }}</x-status-badge>
                                    @if ($entry->review_reason)<span class="block text-xs text-warning">{{ $entry->review_reason }}</span>@endif
                                </td>
                                <td class="text-sm tabular-nums">
                                    @if ($result)
                                        {{ $result->formattedValue() }}@if ($result->placement) · {{ __('club.competitions.label.place', ['no' => $result->placement]) }}@endif
                                        @if (! $result->isConfirmed())<x-status-badge tone="warning" size="xs" :label="__('club.competitions.label.unconfirmed')" />@endif
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if ($canManage && ! $isCancelled)
                                        @if ($entry->status === \App\Enums\Club\ClubEntryStatus::NeedsReview)
                                            <form method="POST" action="{{ route('club.competitions.entries.clear', [$event, $entry]) }}" class="inline-flex items-center gap-1" data-entry-form>
                                                @csrf
                                                <input type="text" name="note" maxlength="255" class="input input-bordered input-xs w-40" placeholder="{{ __('club.competitions.field.clear_note') }}" aria-label="{{ __('club.competitions.field.clear_note') }}">
                                                <x-icon-btn type="submit" icon="check" tone="outline" size="xs" :label="__('club.competitions.action.clear')" />
                                            </form>
                                        @endif
                                        @if ($entry->status->isActive())
                                            <x-icon-btn icon="scoreboard" tone="ghost" size="xs" data-entry-modal-trigger :href="route('club.competitions.entries.result.edit', [$event, $entry])" :label="__('club.competitions.action.record_result')" />
                                            <x-action-form :action="route('club.competitions.entries.withdraw', [$event, $entry])" class="inline">
                                                <x-icon-btn type="submit" icon="person_remove" tone="ghost" size="xs" :label="__('club.competitions.action.withdraw')" />
                                            </x-action-form>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-table.empty icon="how_to_reg" :colspan="4" :title="__('club.competitions.empty.entries')" compact />
                        @endforelse
                    </x-table>
                @empty
                    <p class="text-sm text-muted">{{ __('club.competitions.empty.disciplines') }}</p>
                @endforelse
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.competitions.card.competition')" icon="info">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('club.teams.field.profile')" :value="$competition->profile?->name ?? '–'" />
                    <x-detail-grid.row :label="__('club.competitions.field.organizer')" :value="$competition->organizer ?? '–'" />
                    <x-detail-grid.row :label="__('club.competitions.field.venue')" :value="$competition->venue ?? ($event->rooms->pluck('name')->implode(', ') ?: '–')" />
                    <x-detail-grid.row :label="__('club.competitions.field.entry_fee')" :value="$competition->entry_fee?->format() ?? '–'" />
                    <x-detail-grid.row :label="__('club.competitions.field.requires_start_right')" :value="$competition->requires_start_right ? __('club.label.yes') : __('club.label.no')" />
                    <x-detail-grid.row :label="__('club.competitions.field.entry_deadline_hours')" :value="$details->registrationClosesAt($event)?->orgTz()->format('d.m.Y H:i') ?? '–'" />
                    <x-detail-grid.row :label="__('club.events.field.leader')" :value="$event->responsibleUser?->name ?? '–'" />
                    <x-detail-grid.row :label="__('club.events.field.groups')">
                        @forelse ($event->clubGroups as $group)
                            <a href="{{ route('club.groups.show', $group) }}" class="badge badge-ghost badge-sm">{{ $group->name }}</a>
                        @empty
                            <span class="text-muted">{{ __('club.events.label.all_members') }}</span>
                        @endforelse
                    </x-detail-grid.row>
                    @if ($competition->notes)
                        <x-detail-grid.row :label="__('club.field.notes')" :value="$competition->notes" />
                    @endif
                </x-detail-grid>
                <p class="mt-3 text-xs text-muted">{{ __('club.competitions.hint.entry_fee_position') }}</p>
            </x-card>

            @include('club.events._resources_card', ['canParticipants' => $canManage])
        </div>
    </div>
</x-page-shell>
@endsection
