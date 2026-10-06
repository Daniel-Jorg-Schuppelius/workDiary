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
        <x-page-toolbar :subtitle="$ticket->title"
                        back-route="service-tickets.index" :back-label="__('Zurück')">
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <span class="font-mono text-sm">{{ $ticket->ticket_no }}</span>
            <x-status-badge tone="plain" size="md">{{ $ticket->priority->label() }}</x-status-badge>
            <x-status-badge size="md" outline>{{ $ticket->status->label() }}</x-status-badge>
            <x-status-badge tone="ghost" size="md">{{ $ticket->source->label() }}</x-status-badge>
            <span class="ml-auto {{ $slaStatus->textClass() }} font-medium">
                <x-icon name="timer" class="align-middle text-[16px]" />
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

        <x-detail-grid layout="cells">
            <x-detail-grid.row :label="__('Gemeldet von')">{{ $ticket->reportedBy?->name ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Gemeldet am')">{{ $ticket->reported_at?->orgTz()->translatedFormat('d.m.Y H:i') ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Bearbeiter')">{{ $ticket->assignedTo?->name ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Bestätigt')">{{ $ticket->acknowledged_at?->orgTz()->translatedFormat('d.m.Y H:i') ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Reaktion bis')" class="flex items-center gap-2">{{ $reactDue?->translatedFormat('d.m.Y H:i') ?: '—' }}@if ($reactDue)<x-status-badge :tone="$reactionStatus->tone()" size="sm" outline>{{ $reactionStatus->label() }}</x-status-badge>@endif</x-detail-grid.row>
            <x-detail-grid.row :label="__('Lösung bis')">{{ $resDue?->translatedFormat('d.m.Y H:i') ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Asset')">{{ $ticket->asset?->name ?: '—' }}</x-detail-grid.row>
            <x-detail-grid.row :label="__('Kunde')">{{ $ticket->customer?->name ?: '—' }}</x-detail-grid.row>
        </x-detail-grid>

        @if ($ticket->description)
            <div class="mt-4">
                <div class="text-xs uppercase text-muted mb-1">{{ __('Beschreibung') }}</div>
                <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ $ticket->description }}</div>
            </div>
        @endif
    </x-card>

    {{-- SLA-Uhr (MVP-160): Fristen + Snapshot + Wartefelder + offene Pausen. --}}
    @include('service-tickets._sla_clock')

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
                <div class="w-64">
                    <x-user-select name="assignee_user_id" :users="$orgUsers" value-key="sqid"
                                   :selected="$ticket->assignedTo?->sqid" class="select-sm"
                                   :placeholder="__('Unzugewiesen')" />
                </div>
                <x-button type="submit" tone="plain">{{ __('Speichern') }}</x-button>
            </form>
        </x-card>
    @endif

    {{-- Ticket-Detail-Widgets (MVP-160): Beobachter, Verknüpfungen, Major Incident. --}}
    <div class="grid gap-4 lg:grid-cols-2">
        @include('service-tickets._watchers')
        @include('service-tickets._links')
    </div>
    @include('service-tickets._major_incident')

    {{-- Timeline (MVP-152): Konversation + Status-Audits + SLA + Anhänge
         gemischt; Antwort vs. interne Notiz bleiben getrennte Typen/Aktionen —
         Notizen sind nie kundensichtbar. --}}
    <x-card :title="__('Verlauf')" icon="history">
        @include('service-tickets._timeline')

        @if ($canUpdate)
            <form method="POST" action="{{ route('helpdesk.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="mt-3">
                @csrf
                <label class="fieldset-label" for="reply-body">{{ __('Antwort (kundensichtbar)') }}</label>
                <textarea id="reply-body" name="body" rows="3" required minlength="2" class="textarea textarea-bordered w-full"></textarea>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <input aria-label="{{ __('Empfänger (optional, versendet per Mail)') }}" name="to[]" type="email" placeholder="{{ __('Empfänger (optional, versendet per Mail)') }}" class="input input-sm input-bordered flex-1">
                    <input name="files[]" type="file" multiple class="file-input file-input-sm file-input-bordered"
                           aria-label="{{ __('Anhänge (optional)') }}">
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Antworten') }}</x-icon-btn>
                </div>
                @error('files')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
                @error('files.*')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
            </form>
        @endif
        @if ($canNote)
            <form method="POST" action="{{ route('helpdesk.tickets.note', $ticket) }}" enctype="multipart/form-data" class="mt-3">
                @csrf
                <label class="fieldset-label" for="note-body">{{ __('Interne Notiz (nie kundensichtbar)') }}</label>
                <textarea id="note-body" name="body" rows="2" required minlength="2" class="textarea textarea-bordered w-full"></textarea>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <input name="files[]" type="file" multiple class="file-input file-input-sm file-input-bordered"
                           aria-label="{{ __('Anhänge (optional)') }}">
                    <x-icon-btn icon="sticky_note_2" tone="ghost" size="sm" type="submit" show-label>{{ __('Notiz speichern') }}</x-icon-btn>
                </div>
            </form>
        @endif
    </x-card>
</x-page-shell>
@endsection
