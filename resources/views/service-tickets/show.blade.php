{{--
  Created on   : Wed May 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Ticket :no', ['no' => $ticket->ticket_no]))
@section('nav-title', __('Ticket :no', ['no' => $ticket->ticket_no]))

@section('content')
@php
    $resDue = $ticket->resolution_due_at;
    $reactDue = $ticket->reaction_due_at;
    $slaStatus = $ticket->slaStatus();
    $reactionStatus = $ticket->slaReactionStatus();
    $remaining = $ticket->slaMinutesRemaining();
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="$ticket->title">
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('service-tickets.index')"
                            show-label>{{ __('Zurück') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-mono text-sm">{{ $ticket->ticket_no }}</span>
            <span class="badge">{{ $ticket->priority->label() }}</span>
            <x-status-badge size="md" outline>{{ $ticket->status->label() }}</x-status-badge>
            <x-status-badge tone="ghost" size="md">{{ $ticket->source->label() }}</x-status-badge>
            <span class="ml-auto {{ $slaStatus->textClass() }} font-medium">
                <span class="material-symbols-outlined align-middle text-[16px]">timer</span>
                {{ $slaStatus->label() }}
                @if ($resDue)
                    · {{ __('Lösung bis :date', ['date' => $resDue->translatedFormat('d.m.Y H:i')]) }}
                @endif
                @if ($remaining !== null && $slaStatus->value !== 'met' && $slaStatus->value !== 'none')
                    · {{ $remaining < 0 ? __('sla.overdue_by', ['min' => abs($remaining)]) : __('sla.remaining', ['min' => $remaining]) }}
                @endif
            </span>
        </div>

        <div class="divider my-3"></div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-base-content/60">{{ __('Gemeldet von') }}</dt><dd>{{ $ticket->reportedBy?->name ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Gemeldet am') }}</dt><dd>{{ $ticket->reported_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Bearbeiter') }}</dt><dd>{{ $ticket->assignedTo?->name ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Bestätigt') }}</dt><dd>{{ $ticket->acknowledged_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Reaktion bis') }}</dt><dd class="flex items-center gap-2">{{ $reactDue?->translatedFormat('d.m.Y H:i') ?: '—' }}@if ($reactDue)<x-status-badge :tone="$reactionStatus->tone()" size="sm" outline>{{ $reactionStatus->label() }}</x-status-badge>@endif</dd></div>
            <div><dt class="text-base-content/60">{{ __('Lösung bis') }}</dt><dd>{{ $resDue?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Asset') }}</dt><dd>{{ $ticket->asset?->name ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Kunde') }}</dt><dd>{{ $ticket->customer?->name ?: '—' }}</dd></div>
        </dl>

        @if ($ticket->description)
            <div class="mt-4">
                <div class="text-xs uppercase text-base-content/60 mb-1">{{ __('Beschreibung') }}</div>
                <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ $ticket->description }}</div>
            </div>
        @endif
    </x-card>

    @if ($canUpdate)
        <x-card>
            <h3 class="font-semibold mb-3">{{ __('Status ändern') }}</h3>
            <form method="POST" action="{{ route('service-tickets.transition', $ticket) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <select name="status" class="select select-sm select-bordered">
                    @foreach ($statusOptions as $val => $label)
                        <option value="{{ $val }}" @selected($ticket->status->value === $val)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-button tone="primary" size="sm" type="submit">{{ __('Übernehmen') }}</x-button>
                @error('status')<p class="text-error text-xs w-full">{{ $message }}</p>@enderror
            </form>
        </x-card>
    @endif

    @if ($canAssign)
        <x-card>
            <h3 class="font-semibold mb-3">{{ __('Zuweisen') }}</h3>
            <form method="POST" action="{{ route('service-tickets.assign', $ticket) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="number" min="1" name="assignee_user_id" value="{{ $ticket->assigned_to_user_id }}"
                       class="input input-sm input-bordered w-40" placeholder="{{ __('User-ID') }}">
                <button class="btn btn-sm" type="submit">{{ __('Speichern') }}</button>
            </form>
        </x-card>
    @endif

    {{-- Konversation (Feature 065, P2): Antwort vs. interne Notiz sind
         getrennte Typen/Aktionen — Notizen sind nie kundensichtbar. --}}
    <x-card :title="__('Konversation')">
        @if ($messages->isEmpty())
            <p class="text-sm text-base-content/50">{{ __('Noch keine Nachrichten.') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($messages as $message)
                    <li class="rounded border px-3 py-2 {{ $message->kind->value === 'internal_note' ? 'border-warning/60 bg-warning/5' : 'border-base-300' }}">
                        <div class="mb-1 flex items-center gap-2 text-xs text-base-content/60">
                            <x-status-badge :tone="match ($message->kind->value) {
                                'internal_note' => 'warning',
                                'system_event' => 'neutral',
                                default => 'info',
                            }" size="xs">{{ $message->kind->label() }}</x-status-badge>
                            <span>{{ $message->created_at->isoFormat('L LT') }}</span>
                            <span>{{ $message->channel }}</span>
                            @if ($message->delivery_status)
                                <span>· {{ $message->delivery_status }}</span>
                            @endif
                        </div>
                        <p class="whitespace-pre-line text-sm">{{ $message->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canUpdate)
            <form method="POST" action="{{ route('helpdesk.tickets.reply', $ticket) }}" class="mt-3">
                @csrf
                <label class="fieldset-label" for="reply-body">{{ __('Antwort (kundensichtbar)') }}</label>
                <textarea id="reply-body" name="body" rows="3" required minlength="2" class="textarea textarea-bordered w-full"></textarea>
                <div class="mt-1 flex items-center gap-2">
                    <input name="to[]" type="email" placeholder="{{ __('Empfänger (optional, versendet per Mail)') }}" class="input input-sm input-bordered flex-1">
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Antworten') }}</x-icon-btn>
                </div>
            </form>
        @endif
        @if ($canNote)
            <form method="POST" action="{{ route('helpdesk.tickets.note', $ticket) }}" class="mt-3">
                @csrf
                <label class="fieldset-label" for="note-body">{{ __('Interne Notiz (nie kundensichtbar)') }}</label>
                <textarea id="note-body" name="body" rows="2" required minlength="2" class="textarea textarea-bordered w-full"></textarea>
                <x-icon-btn icon="sticky_note_2" tone="ghost" size="sm" type="submit" show-label class="mt-1">{{ __('Notiz speichern') }}</x-icon-btn>
            </form>
        @endif
    </x-card>
</x-page-shell>
@endsection
