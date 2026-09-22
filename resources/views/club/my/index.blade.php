{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  „Mein Verein“ (Feature 159, MVP-845): nächste Termine mit An-/Abmeldung,
  Änderungen, bestätigte Anwesenheit — für das eigene Mitglied oder als
  ausdrücklich gewählte Vertretung. Keine fremden Mitglieder oder Fehlzeiten.
--}}
@extends('layouts.app')
@section('title', __('club.my.title.index'))
@section('nav-title', __('club.my.title.index'))
@section('content')
@php
    $member = $subject->member;
    $hours = intdiv($creditedMinutes, 60);
    $minutes = $creditedMinutes % 60;
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$member->fullName() . ' · ' . $member->displayNo()"
                        :badge="$subject->isSelf() ? __('club.my.label.self') : __('club.my.label.guardian')"
                        :badgeTone="$subject->isSelf() ? 'primary' : 'info'">
            <x-slot:actions>
                @if ($subjects->count() > 1)
                    <form method="POST" action="{{ route('club.my.select') }}" class="flex items-center gap-2">
                        @csrf
                        <label for="club-my-member" class="sr-only">{{ __('club.my.field.member') }}</label>
                        <select id="club-my-member" name="club_member_id" class="select select-sm select-bordered" data-autosubmit>
                            @foreach ($subjects as $option)
                                <option value="{{ $option->member->sqid }}" @selected($option->member->id === $member->id)>{{ $option->member->fullName() }}{{ $option->isSelf() ? '' : ' · ' . __('club.my.label.guardian') }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                @if ($canViewAttendance)
                    <x-icon-btn icon="fact_check" tone="outline" size="sm" :href="route('club.my.attendance')" show-label>{{ __('club.my.action.attendance') }}</x-icon-btn>
                @endif
                <x-help-button topic="club.my" />
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($errors->any())
        <div class="alert alert-error text-sm" role="alert">
            <x-icon name="error" />
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('club.my.card.upcoming')" icon="event" :count="$upcoming->count()">
                <p class="mb-2 text-xs text-muted">{{ __('club.my.hint.upcoming') }}</p>
                <x-table :bare="true" size="sm">
                    <x-slot:head>
                        <tr>
                            <th>{{ __('club.events.field.starts') }}</th>
                            <th>{{ __('club.events.field.title') }}</th>
                            <th>{{ __('club.field.status') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>
                    @forelse ($upcoming as $row)
                        @php
                            $event = $row['event'];
                            $participation = $row['participation'];
                            $details = $event->clubDetails;
                            $active = $participation !== null && $participation->isActive();
                            $registrationCloses = $details?->registrationClosesAt($event);
                            $cancellationCloses = $details?->cancellationClosesAt($event);
                        @endphp
                        <tr class="align-top">
                            <td class="whitespace-nowrap text-sm tabular-nums">
                                {{ $event->started_at->orgTz()->format('d.m.Y H:i') }}
                                <span class="block text-xs text-muted">{{ $event->ended_at->orgTz()->format($event->started_at->orgTz()->isSameDay($event->ended_at->orgTz()) ? 'H:i' : 'd.m.Y H:i') }}</span>
                            </td>
                            <td class="text-sm">
                                <span class="font-medium">{{ $event->title }}</span>
                                @if ($details)
                                    <span class="block text-xs text-muted">{{ $details->kind->label() }}@if ($event->clubGroups->isNotEmpty()) · {{ $event->clubGroups->pluck('name')->join(', ') }}@endif</span>
                                @endif
                                @if ($event->isCancelled())
                                    <x-status-badge tone="error" size="xs" :label="__('club.events.label.cancelled')" />
                                @endif
                                @if ($registrationCloses && ! $event->isCancelled())
                                    <span class="block text-xs text-muted">{{ __('club.my.label.registration_until', ['when' => $registrationCloses->orgTz()->format('d.m.Y H:i')]) }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($participation)
                                    <x-status-badge :tone="$participation->status->tone()" size="sm">{{ $participation->status->label() }}</x-status-badge>
                                @else
                                    <span class="text-xs text-muted">{{ __('club.my.label.not_registered') }}</span>
                                @endif
                            </td>
                            <td class="text-right">
                                @if ($canRegister && ! $event->isCancelled())
                                    @if ($active)
                                        @if ($cancellationCloses === null || $cancellationCloses->isFuture())
                                            <x-action-form :action="route('club.my.cancel', $event)" :confirm="__('club.my.confirm.cancel', ['title' => $event->title])" confirm-icon="person_remove" confirm-tone="warning">
                                                <x-icon-btn type="submit" icon="person_remove" tone="outline" size="xs" class="btn-warning" show-label>{{ __('club.my.action.cancel') }}</x-icon-btn>
                                            </x-action-form>
                                        @else
                                            <span class="text-xs text-muted">{{ __('club.my.label.cancellation_closed') }}</span>
                                        @endif
                                    @elseif ($row['eligible'] && ($registrationCloses === null || $registrationCloses->isFuture()) && $event->started_at->isFuture())
                                        <x-action-form :action="route('club.my.register', $event)">
                                            <x-icon-btn type="submit" icon="how_to_reg" tone="primary" size="xs" show-label>{{ __('club.my.action.register') }}</x-icon-btn>
                                        </x-action-form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty icon="event" :colspan="4" :title="__('club.my.empty.upcoming')" compact />
                    @endforelse
                </x-table>
            </x-card>

            @if ($recent->isNotEmpty())
                <x-card :title="__('club.my.card.recent')" icon="history" :count="$recent->count()">
                    <ul class="space-y-1 text-sm">
                        @foreach ($recent as $row)
                            <li class="flex flex-wrap items-center gap-2">
                                <span class="tabular-nums">{{ $row['event']->started_at->orgTz()->format('d.m.Y H:i') }}</span>
                                <span class="font-medium">{{ $row['event']->title }}</span>
                                @if ($row['participation'])
                                    <x-status-badge :tone="$row['participation']->status->tone()" size="xs">{{ $row['participation']->status->label() }}</x-status-badge>
                                @endif
                                @if ($row['event']->isCancelled())
                                    <x-status-badge tone="error" size="xs" :label="__('club.events.label.cancelled')" />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-4">
            @if ($canViewAttendance)
                <x-card :title="__('club.my.card.attendance')" icon="fact_check">
                    <p class="text-2xl font-semibold tabular-nums">{{ $hours }}:{{ str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) }} <span class="text-sm font-normal text-muted">{{ __('club.my.label.hours') }}</span></p>
                    <p class="text-xs text-muted">{{ __('club.my.hint.attendance', ['from' => $range['from']->format('d.m.Y'), 'to' => $range['to']->format('d.m.Y')]) }}</p>
                    <a href="{{ route('club.my.attendance') }}" class="link link-primary text-sm">{{ __('club.my.action.attendance') }}</a>
                </x-card>
            @endif

            <x-card :title="__('club.my.card.changes')" icon="notifications" :count="$changes->count()">
                @forelse ($changes as $change)
                    <div class="border-b border-base-200 py-2 text-sm last:border-0">
                        <div class="flex items-center gap-2">
                            <x-icon :name="match ($change->kind) { 'cancelled' => 'event_busy', 'promoted' => 'how_to_reg', default => 'update' }" class="text-muted" />
                            <span class="text-xs text-muted">{{ $change->created_at?->orgTz()->format('d.m.Y H:i') }}</span>
                            @if ($change->isFailed())
                                <x-status-badge tone="error" size="xs" :label="__('club.my.label.delivery_failed')" />
                            @endif
                        </div>
                        <p>{{ ! empty($change->payload['message_key']) ? \App\Support\NotificationText::render((string) $change->payload['message_key'], (array) ($change->payload['message_params'] ?? [])) : (string) ($change->payload['message'] ?? '') }}</p>
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('club.my.empty.changes') }}</p>
                @endforelse
                <p class="mt-2 text-xs text-muted">{{ __('club.my.hint.changes') }}</p>
            </x-card>
        </div>
    </div>
</x-page-shell>
@endsection
