{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Detailseite Vereinstermin (Feature 159, MVP-843): Anmeldungen, Warteliste,
  Einladungen und Soll-Liste je Termin; Details, Fristen und Serie rechts.
--}}
@extends('layouts.app')
@section('title', $event->title)
@section('nav-title', $event->title)
@section('content')
@php
    $start = $event->started_at->orgTz();
    $end = $event->ended_at->orgTz();
    $timeLabel = $start->format('d.m.Y H:i') . ' – ' . ($start->isSameDay($end) ? $end->format('H:i') : $end->format('d.m.Y H:i'));
    $roomLabel = $event->rooms->pluck('name')->implode(', ');
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$timeLabel . ($roomLabel !== '' ? ' · ' . $roomLabel : '')"
                        :badge="$isCancelled ? __('club.events.label.cancelled') : $details->kind->label()"
                        :badgeTone="$isCancelled ? 'error' : 'primary'">
            <x-slot:actions>
                @if ($canParticipants && ! $isCancelled)
                    <x-icon-btn icon="person_add" tone="primary" size="sm"
                                data-entry-modal-trigger
                                :href="route('club.events.register.create', $event)"
                                show-label>{{ __('club.events.action.register') }}</x-icon-btn>
                @endif
                @if ($canParticipants)
                    <x-icon-btn icon="fact_check" tone="outline" size="sm"
                                :href="route('club.events.attendance.show', $event)"
                                show-label>{{ __('club.attendance.action.open') }}</x-icon-btn>
                @endif
                @if ($canManage)
                    <x-icon-btn icon="edit" tone="outline" size="sm"
                                data-entry-modal-trigger
                                :href="route('club.events.edit', $event)"
                                show-label>{{ __('club.action.edit') }}</x-icon-btn>
                    @unless ($isCancelled)
                        <x-icon-btn icon="event_busy" tone="outline" size="sm" class="btn-warning"
                                    data-entry-modal-trigger
                                    :href="route('club.events.cancel.edit', $event)"
                                    show-label>{{ __('club.events.action.cancel_event') }}</x-icon-btn>
                    @endunless
                @endif
                <x-icon-btn icon="arrow_back" tone="ghost" size="sm"
                            :href="route('club.events.index')"
                            show-label>{{ __('club.action.back') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.events.field.registered')" icon="how_to_reg" :count="$registered->count()">
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.events.field.source') }}</th>
                            <th>{{ __('club.events.field.registered_at') }}</th>
                            <th>{{ __('club.field.note') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($registered as $participation)
                        <tr>
                            <td class="font-medium">
                                @if ($participation->member)
                                    <a href="{{ route('club.members.show', $participation->member) }}" class="link link-hover">{{ $participation->member->fullName() }}</a>
                                @endif
                                @if ($participation->promoted_at)
                                    <x-status-badge tone="info" size="xs" :label="__('club.events.label.promoted')" />
                                @endif
                            </td>
                            <td class="text-sm">{{ $participation->source->label() }}@if ($participation->registeredBy) · {{ $participation->registeredBy->name }}@endif</td>
                            <td class="text-sm text-base-content/70">{{ $participation->registered_at?->orgTz()->format('d.m.Y H:i') ?? '–' }}</td>
                            <td class="text-sm text-base-content/70">{{ $participation->note ?? '–' }}</td>
                            <td class="text-right">
                                @if ($canParticipants && ! $isCancelled)
                                    <x-action-form :action="route('club.events.participations.cancel', [$event, $participation])" :confirm="__('club.events.confirm.cancel_registration')" confirm-icon="person_remove" confirm-tone="warning">
                                        <x-icon-btn type="submit" icon="person_remove" tone="outline" size="xs" class="btn-warning" :label="__('club.events.action.cancel_registration')" />
                                    </x-action-form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="how_to_reg" :colspan="5" :title="__('club.events.empty.participants')" compact />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('club.events.field.waitlist')" icon="hourglass_top" :count="$waitlisted->count()">
                @forelse ($waitlisted as $index => $participation)
                    <div class="flex flex-wrap items-center gap-2 py-1 text-sm">
                        <span class="badge badge-warning badge-sm">{{ $index + 1 }}</span>
                        @if ($participation->member)
                            <a href="{{ route('club.members.show', $participation->member) }}" class="link link-hover">{{ $participation->member->fullName() }}</a>
                        @endif
                        <span class="text-muted">{{ $participation->registered_at?->orgTz()->format('d.m.Y H:i') }}</span>
                        @if ($canParticipants && ! $isCancelled)
                            <x-action-form :action="route('club.events.participations.cancel', [$event, $participation])" :confirm="__('club.events.confirm.cancel_registration')" confirm-icon="person_remove" confirm-tone="warning" class="ml-auto">
                                <x-icon-btn type="submit" icon="person_remove" tone="outline" size="xs" class="btn-warning" :label="__('club.events.action.cancel_registration')" />
                            </x-action-form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('club.events.empty.waitlist') }}</p>
                @endforelse
            </x-card>

            <x-card :title="__('club.events.field.target_list')" icon="checklist" :count="$targets->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.events.hint.target_list') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.field.member') }}</th>
                            <th>{{ __('club.field.status') }}</th>
                        </tr>
                    </x-slot:head>
                    @forelse ($targets as $member)
                        @php($participation = $byMember->get($member->id))
                        <tr>
                            <td class="font-medium">
                                <a href="{{ route('club.members.show', $member) }}" class="link link-hover">{{ $member->fullName() }}</a>
                                <span class="font-mono text-xs text-muted">{{ $member->displayNo() }}</span>
                            </td>
                            <td>
                                @if ($participation)
                                    <x-status-badge :tone="$participation->status->tone()" size="xs">{{ $participation->status->label() }}</x-status-badge>
                                @else
                                    <span class="text-xs text-muted">{{ __('club.events.label.not_registered') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="checklist" :colspan="2" :title="__('club.events.empty.targets')" compact />
                    @endforelse
                </x-table>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card :title="__('club.events.card.details')" icon="info">
                <x-detail-grid>
                    <x-detail-grid.row :label="__('club.events.field.kind')" :value="$details->kind->label()" />
                    <x-detail-grid.row :label="__('club.events.field.visibility')" :value="$details->visibility->label()" />
                    <x-detail-grid.row :label="__('club.events.field.groups')">
                        @forelse ($event->clubGroups as $group)
                            <a href="{{ route('club.groups.show', $group) }}" class="badge badge-ghost badge-sm">{{ $group->name }}</a>
                        @empty
                            <span class="text-muted">{{ $details->visibility === \App\Enums\Club\ClubEventVisibility::Club ? __('club.events.label.all_members') : '–' }}</span>
                        @endforelse
                    </x-detail-grid.row>
                    <x-detail-grid.row :label="__('club.events.field.leader')" :value="$event->responsibleUser?->name ?? '–'" />
                    <x-detail-grid.row :label="__('club.events.field.room')" :value="$roomLabel !== '' ? $roomLabel : '–'" />
                    <x-detail-grid.row :label="__('club.events.field.occupancy')" :value="$seats['max'] !== null ? __('club.events.label.seats', ['taken' => $seats['taken'], 'max' => $seats['max']]) : __('club.events.label.seats_unlimited', ['taken' => $seats['taken']])" />
                    @if ($details->registration_lead_hours !== null)
                        <x-detail-grid.row :label="__('club.events.field.registration_lead_hours')" :value="$details->registrationClosesAt($event)?->setTimezone($start->timezone)->format('d.m.Y H:i')" />
                    @endif
                    @if ($details->cancellation_lead_hours !== null)
                        <x-detail-grid.row :label="__('club.events.field.cancellation_lead_hours')" :value="$details->cancellationClosesAt($event)?->setTimezone($start->timezone)->format('d.m.Y H:i')" />
                    @endif
                    @if ($event->series_id !== null)
                        <x-detail-grid.row :label="__('club.events.label.series')">
                            <a href="{{ route('club.events.show', $event->series) }}" class="link link-hover">{{ __('club.events.label.occurrence') }}</a>
                        </x-detail-grid.row>
                    @elseif ($occurrenceCount)
                        <x-detail-grid.row :label="__('club.events.label.series')" :value="__('club.events.label.occurrences', ['count' => $occurrenceCount])" />
                    @endif
                    @if ($isCancelled)
                        <x-detail-grid.row :label="__('club.events.field.cancel_reason')" :value="$event->cancel_reason ?? '–'" />
                    @endif
                    @if ($event->description)
                        <x-detail-grid.row :label="__('club.field.description')" :value="$event->description" />
                    @endif
                </x-detail-grid>
            </x-card>

            @if ($invited->isNotEmpty() || $cancelledCount > 0)
                <x-card :title="__('club.events.field.invited')" icon="mail" :count="$invited->count()">
                    <ul class="space-y-1 text-sm">
                        @foreach ($invited as $participation)
                            <li>
                                @if ($participation->member)
                                    <a href="{{ route('club.members.show', $participation->member) }}" class="link link-hover">{{ $participation->member->fullName() }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($cancelledCount > 0)
                        <p class="mt-2 text-xs text-muted">{{ __('club.events.label.cancelled_count', ['count' => $cancelledCount]) }}</p>
                    @endif
                </x-card>
            @endif

            @if ($canParticipants && $clubNotifications->isNotEmpty())
                {{-- Zustellprotokoll (MVP-845): Fehler bleiben sichtbar, kein Gelesen-Status. --}}
                <x-card :title="__('club.my.card.deliveries')" icon="outgoing_mail" :count="$clubNotifications->count()">
                    <ul class="space-y-1 text-xs">
                        @foreach ($clubNotifications as $delivery)
                            <li class="flex flex-wrap items-center gap-2">
                                <span class="tabular-nums text-muted">{{ $delivery->created_at?->orgTz()->format('d.m.Y H:i') }}</span>
                                <span class="font-medium">{{ $delivery->member?->fullName() }}</span>
                                <span class="badge badge-ghost badge-xs">{{ __('club.my.kind.' . $delivery->kind) }}</span>
                                <span class="text-muted">{{ $delivery->recipientLabel() }}</span>
                                @if ($delivery->isFailed())
                                    <x-status-badge tone="error" size="xs" :label="__('club.my.label.delivery_failed')" :title="$delivery->error" />
                                @else
                                    <x-status-badge tone="success" size="xs" :label="__('club.my.label.delivered')" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
