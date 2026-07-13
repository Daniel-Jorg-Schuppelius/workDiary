{{--
  Created on   : Sun Jul 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

{{-- Queue-Board (Feature 065, MVP-160): Spalten je Status (Reihenfolge =
     Zustandsmaschine), Checkbox je Karte + x-bulk-toolbar für Massenzuweisung
     und Queue-Wechsel. Bewusst OHNE Drag&Drop — Statuswechsel bleiben auf der
     Detailseite. --}}

@extends('layouts.app')
@section('title', __('Queue-Board'))
@section('nav-title', __('Queue-Board'))
@section('wrapper-height-class', 'wd-page-fill')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')

@section('content')
<x-index-page :subtitle="__('Tickets nach Status — mit Massenzuweisung und Queue-Wechsel.')">
    <x-filter-bar :action="route('helpdesk.board.index')" :reset="route('helpdesk.board.index')">
        <input type="text" name="q" value="{{ $filters['q'] }}"
               class="input input-sm input-bordered w-48 shrink-0"
               placeholder="{{ __('Suche') }}" aria-label="{{ __('Suche') }}" />

        <select name="queue" class="select select-sm select-bordered w-44 shrink-0" aria-label="{{ __('Queue') }}">
            <option value="">{{ __('Alle Queues') }}</option>
            @foreach ($queues as $queue)
                <option value="{{ $queue->sqid }}" @selected($filters['queue'] === $queue->sqid)>{{ $queue->name }}</option>
            @endforeach
        </select>

        <select name="assignee" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Bearbeiter') }}">
            <option value="">{{ __('Alle Bearbeiter') }}</option>
            <option value="me" @selected($filters['assignee'] === 'me')>{{ __('Mir zugewiesen') }}</option>
            <option value="unassigned" @selected($filters['assignee'] === 'unassigned')>{{ __('Unzugewiesen') }}</option>
        </select>

        <select name="priority" class="select select-sm select-bordered w-32 shrink-0" aria-label="{{ __('Priorität') }}">
            <option value="">{{ __('Alle Prio.') }}</option>
            @foreach ($priorityOptions as $priority)
                <option value="{{ $priority->value }}" @selected($filters['priority'] === $priority->value)>{{ $priority->label() }}</option>
            @endforeach
        </select>

        <select name="kind" class="select select-sm select-bordered w-40 shrink-0" aria-label="{{ __('Ticketart') }}">
            <option value="">{{ __('Alle Arten') }}</option>
            @foreach ($kindOptions as $kind)
                <option value="{{ $kind->value }}" @selected($filters['kind'] === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </select>
    </x-filter-bar>

    @if ($isLimited)
        <div class="rounded-box border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-base-content/80">
            {{ __('Es werden maximal :count Tickets angezeigt. Verfeinere die Filter für vollständigere Ergebnisse.', ['count' => $maxTickets]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('helpdesk.board.bulk-assign') }}" data-bulk-form
          class="min-h-0 flex-1 flex flex-col gap-3">
        @csrf
        @if ($canBulk)
            <x-bulk-toolbar :label="__(':n Tickets ausgewählt')">
                <x-slot:actions>
                    <select name="assignee" class="select select-sm select-bordered w-44"
                            aria-label="{{ __('Bearbeiter wählen') }}">
                        <option value="">{{ __('Bearbeiter wählen…') }}</option>
                        @foreach ($orgUsers as $orgUser)
                            <option value="{{ $orgUser->sqid }}">{{ $orgUser->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" formaction="{{ route('helpdesk.board.bulk-assign') }}"
                            class="btn btn-primary btn-sm">
                        <x-icon name="person_add" /> {{ __('Zuweisen') }}
                    </button>
                    <select name="queue" class="select select-sm select-bordered w-44"
                            aria-label="{{ __('Queue wählen') }}">
                        <option value="">{{ __('Queue wählen…') }}</option>
                        @foreach ($queues as $queue)
                            <option value="{{ $queue->sqid }}">{{ $queue->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" formaction="{{ route('helpdesk.board.bulk-queue') }}"
                            class="btn btn-secondary btn-sm">
                        <x-icon name="move_down" /> {{ __('Queue wechseln') }}
                    </button>
                </x-slot:actions>
            </x-bulk-toolbar>
            @error('assignee')<p class="text-error text-xs">{{ $message }}</p>@enderror
            @error('queue')<p class="text-error text-xs">{{ $message }}</p>@enderror
            @error('ids')<p class="text-error text-xs">{{ $message }}</p>@enderror
        @endif

        <div class="flex min-h-0 flex-1 gap-3 overflow-x-auto pb-2">
            @foreach ($columns as $status)
                @php $items = $byStatus->get($status->value, collect()); @endphp
                <section class="flex min-h-0 w-72 shrink-0 flex-col rounded-box border border-base-300 bg-base-100 shadow-xs">
                    <header class="flex items-center justify-between border-b border-base-300 px-3 py-2">
                        <span class="font-['Space_Grotesk'] font-semibold text-sm">{{ $status->label() }}</span>
                        <span class="badge badge-sm">{{ $items->count() }}</span>
                    </header>
                    <div class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto p-2">
                        @forelse ($items as $ticket)
                            @php
                                $slaStatus = $ticket->slaStatus();
                                $remaining = $ticket->slaMinutesRemaining();
                            @endphp
                            <article class="rounded-box border border-base-300 bg-base-200/40 p-2 text-sm">
                                <div class="flex items-start gap-2">
                                    @if ($canBulk)
                                        <input type="checkbox" class="checkbox checkbox-sm mt-0.5"
                                               data-bulk-checkbox name="ids[]" value="{{ $ticket->sqid }}"
                                               aria-label="{{ __('Ticket :no auswählen', ['no' => $ticket->ticket_no]) }}">
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('service-tickets.show', $ticket) }}"
                                           class="link link-hover font-mono text-xs">{{ $ticket->ticket_no }}</a>
                                        <a href="{{ route('service-tickets.show', $ticket) }}"
                                           class="link link-hover block truncate font-medium">{{ $ticket->title }}</a>
                                        <div class="mt-1 flex flex-wrap items-center gap-1">
                                            <span class="badge badge-xs">{{ $ticket->priority->label() }}</span>
                                            @if ($ticket->queue)
                                                <x-status-badge tone="ghost" size="xs">{{ $ticket->queue->name }}</x-status-badge>
                                            @endif
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center justify-between gap-1 text-xs text-base-content/60">
                                            <span class="truncate">{{ $ticket->assignedTo?->name ?: __('Unzugewiesen') }}</span>
                                            @if ($remaining !== null && $slaStatus->value !== 'met' && $slaStatus->value !== 'none')
                                                <span class="{{ $slaStatus->textClass() }} whitespace-nowrap">
                                                    {{ $remaining < 0 ? __('sla.overdue_by', ['min' => abs($remaining)]) : __('sla.remaining', ['min' => $remaining]) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <p class="px-2 py-4 text-center text-xs text-base-content/50">{{ __('Keine Tickets') }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </form>
</x-index-page>
@endsection
