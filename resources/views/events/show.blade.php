{{--
  Created on   : Tue Jun 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', $event->title)
@section('nav-title', $event->title)

@php
    /** @var \App\Models\Event $event */
@endphp

@section('content')
    <x-page-shell>
        <x-slot:toolbar>
            <x-page-toolbar>
                <x-slot:actions>
                    <x-icon-btn icon="arrow_back" tone="ghost" size="sm" :href="route('events.index')" show-label>{{ __('Zurück') }}</x-icon-btn>
                    @can('update', $event)
                        <x-icon-btn icon="edit" tone="primary" size="sm" data-entry-modal-trigger :href="route('events.edit', $event).'?dialog=1'" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                    @endcan
                </x-slot:actions>
            </x-page-toolbar>
        </x-slot:toolbar>

        <div class="card bg-base-100 shadow">
            <div class="card-body gap-2">
                <div class="text-xs uppercase tracking-wide opacity-70">{{ $event->event_type?->label() }}</div>
                <h2 class="text-2xl font-bold">{{ $event->title }}</h2>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <x-status-badge :status="$event->status?->value" :label="$event->status?->label()" />
                    @if ($event->is_mandatory)
                        <x-status-badge tone="warning">{{ __('Pflicht') }}</x-status-badge>
                    @endif
                    @if ($event->category)
                        <span class="badge" style="background:{{ $event->category->color ?? '#999' }};color:#fff">{{ $event->category->name }}</span>
                    @endif
                    <span class="opacity-70">{{ $event->visibility?->label() }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="card bg-base-100 shadow lg:col-span-2">
                <div class="card-body">
                    <h3 class="card-title text-base">{{ __('Details') }}</h3>
                    <dl class="grid grid-cols-2 gap-y-2 text-sm">
                        <dt class="opacity-70">{{ __('Beginn') }}</dt>
                        <dd>{{ $event->started_at?->isoFormat('LLLL') }}</dd>
                        <dt class="opacity-70">{{ __('Ende') }}</dt>
                        <dd>{{ $event->ended_at?->isoFormat('LLLL') }}</dd>
                        @if ($event->topic)
                            <dt class="opacity-70">{{ __('Thema') }}</dt>
                            <dd>{{ $event->topic }}</dd>
                        @endif
                        <dt class="opacity-70">{{ __('Verantwortlich') }}</dt>
                        <dd>{{ $event->responsibleUser?->name ?? '—' }}</dd>
                        @if ($event->customer)
                            <dt class="opacity-70">{{ __('Externer Anbieter') }}</dt>
                            <dd>{{ $event->customer->name }}</dd>
                        @endif
                        @if ($event->external_contact_note)
                            <dt class="opacity-70">{{ __('Externer Kontakt') }}</dt>
                            <dd>{{ $event->external_contact_note }}</dd>
                        @endif
                        @if ($event->max_participants)
                            <dt class="opacity-70">{{ __('Max. Teilnehmer') }}</dt>
                            <dd>{{ $event->max_participants }}</dd>
                        @endif
                        @if ($event->recurrence_rule)
                            <dt class="opacity-70">{{ __('Wiederholung') }}</dt>
                            <dd><code class="text-xs">{{ $event->recurrence_rule }}</code></dd>
                        @endif
                    </dl>

                    @if ($event->description)
                        <div class="divider my-2"></div>
                        <p class="whitespace-pre-line text-sm">{{ $event->description }}</p>
                    @endif
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <h3 class="card-title text-base">
                        <x-icon name="meeting_room" /> {{ __('Räume') }}
                    </h3>
                    @forelse ($event->rooms as $room)
                        <div class="flex items-center justify-between border-b border-base-200 py-2 last:border-0">
                            <div>
                                <div class="font-semibold">{{ $room->name }}</div>
                                <div class="text-xs opacity-70">
                                    {{ $room->building }} {{ $room->floor ? '· '.$room->floor : '' }}
                                    @if ($room->capacity) · {{ $room->capacity }} {{ __('Plätze') }} @endif
                                </div>
                            </div>
                            <div class="text-xs opacity-70 text-right">
                                {{ optional($room->pivot->started_at)->isoFormat('HH:mm') }} –
                                {{ optional($room->pivot->ended_at)->isoFormat('HH:mm') }}
                            </div>
                        </div>
                    @empty
                        <p class="text-sm opacity-70">{{ __('Keine Räume gebucht.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card bg-base-100 shadow">
            <div class="card-body gap-3">
                <div class="flex items-center justify-between">
                    <h3 class="card-title text-base">
                        <x-icon name="group" /> {{ __('Teilnehmer') }}
                    </h3>
                    <span class="text-sm opacity-70">{{ $event->participants->count() }}{{ $event->max_participants ? ' / '.$event->max_participants : '' }}</span>
                </div>

                <x-table size="sm" :zebra="true" table-sort="client">
                    <x-slot:head>
                        <tr>
                            <x-table.th sort type="string" default="asc">{{ __('Name') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Rolle') }}</x-table.th>
                            <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                            <x-table.th sort type="date">{{ __('Zertifikat bis') }}</x-table.th>
                            <th class="w-32 text-right">{{ __('Aktion') }}</th>
                        </tr>
                    </x-slot:head>
                        @forelse ($event->participants as $p)
                            <tr class="hover">
                                <td class="font-semibold">{{ $p->name }}</td>
                                <td>{{ $p->pivot->role }}</td>
                                <td><x-status-badge :status="$p->pivot->status" :label="$p->pivot->status" /></td>
                                <td @if ($p->pivot->certificate_expires_at) data-sort-value="{{ \Carbon\Carbon::parse($p->pivot->certificate_expires_at)->format('Y-m-d') }}" @endif>
                                    @if ($p->pivot->certificate_expires_at)
                                        {{ \Carbon\Carbon::parse($p->pivot->certificate_expires_at)->isoFormat('LL') }}
                                    @else — @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    @can('manageParticipants', $event)
                                        <form method="POST" action="{{ route('events.participants.attended', [$event, $p]) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <x-icon-btn type="submit" icon="check_circle" tone="success" :label="__('Anwesend')" />
                                        </form>
                                        <form method="POST" action="{{ route('events.participants.no-show', [$event, $p]) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <x-icon-btn type="submit" icon="person_off" tone="warning" :label="__('Nicht erschienen')" />
                                        </form>
                                    @endcan
                                    @can('issueCertificate', $event)
                                        <form method="POST" action="{{ route('events.participants.certificate', [$event, $p]) }}" class="inline">
                                            @csrf
                                            <x-icon-btn type="submit" icon="workspace_premium" tone="info" :label="__('Zertifikat ausstellen')" />
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <x-table.empty :colspan="5"
                                icon='<span class="material-symbols-outlined" aria-hidden="true">group</span>'
                                :title="__('Noch keine Teilnehmer')" compact />
                        @endforelse
                </x-table>
            </div>
        </div>
    </x-page-shell>
@endsection
