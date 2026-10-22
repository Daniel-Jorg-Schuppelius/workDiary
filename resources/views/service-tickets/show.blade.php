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
            @can('delete', $ticket)
                <x-slot:actions>
                    <x-action-form :action="route('service-tickets.destroy', $ticket)" method="DELETE" :confirm="__('Ticket wirklich löschen?')"
                                   confirm-icon="delete" confirm-tone="error" :confirm-label="__('Löschen')">
                        <x-icon-btn placement="danger" icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                    </x-action-form>
                </x-slot:actions>
            @endcan
        </x-page-toolbar>
    </x-slot:toolbar>

    @include('customer-intakes._origin', ['subject' => $ticket])

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
            @if ($ticket->businessService || $services->isNotEmpty())
                <x-detail-grid.row :label="__('helpdesk.service.label')">{{ $ticket->businessService?->name ?: '—' }}@if ($ticket->serviceOffering) · {{ $ticket->serviceOffering->name }}@endif</x-detail-grid.row>
            @endif
        </x-detail-grid>

        @if ($ticket->description)
            <div class="mt-4">
                <div class="text-xs uppercase text-muted mb-1">{{ __('Beschreibung') }}</div>
                <div class="prose prose-sm max-w-none whitespace-pre-wrap">{{ $ticket->description }}</div>
            </div>
        @endif
    </x-card>

    {{-- Materialvorschlag (MVP-1284): fällige Verschleißteile des Geräts am Ticket. --}}
    @if ($ticket->asset)
        @include('assets.components._suggestion_card', ['asset' => $ticket->asset])
    @endif

    {{-- Auswirkungsanalyse (MVP-1256): wer hängt vom betroffenen Fachdienst ab. --}}
    @if ($ticket->businessService)
        <x-card data-ticket-impact>
            <div class="mb-2 flex items-center gap-2">
                <x-icon name="account_tree" class="text-muted" />
                <h3 class="font-semibold">{{ __('helpdesk.impact.title') }}</h3>
                <span class="flex-1"></span>
                <a class="link text-sm" href="{{ route('servicedesk.service-map.index') }}">{{ __('helpdesk.map.title') }}</a>
            </div>
            @if ($impact === [])
                <x-empty-state compact icon="account_tree" :message="__('helpdesk.impact.none', ['service' => $ticket->businessService->name])" />
            @else
                <p class="text-sm">{{ __('helpdesk.impact.affected', ['service' => $ticket->businessService->name]) }}</p>
                <ul class="mt-2 flex flex-wrap gap-2">
                    @foreach ($impact as $row)
                        <li>
                            <a class="badge badge-outline gap-1" href="{{ route('service-tickets.index', ['service' => $row['service']->sqid]) }}">
                                {{ $row['service']->name }}
                                @if ($row['openTickets'] > 0)<span class="text-warning">· {{ trans_choice('helpdesk.impact.openTickets', $row['openTickets'], ['count' => $row['openTickets']]) }}</span>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif

    {{-- SLA-Uhr (MVP-160): Fristen + Snapshot + Wartefelder + offene Pausen. --}}
    @include('service-tickets._sla_clock')

    @include('service-tickets._time_entries')

    @if ($canUpdate)
        {{-- Klassifizierung (ITIL): Auswirkung × Dringlichkeit ergibt die Priorität, übersteuern wird protokolliert (Vollscan 2026-10-21). --}}
        <x-card>
            <h3 class="font-semibold mb-3">{{ __('Klassifizierung') }}</h3>
            <form method="POST" action="{{ route('service-tickets.classify', $ticket) }}" class="grid gap-2 md:grid-cols-3">
                @csrf
                <x-select-field name="impact" id="ticket-impact" :label="__('Auswirkung')" required>
                    @foreach (\App\Enums\ServiceTicket\TicketSeverity::cases() as $severity)
                        <option value="{{ $severity->value }}" @selected(($ticket->impact?->value ?? 2) === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="urgency" id="ticket-urgency" :label="__('Dringlichkeit')" required>
                    @foreach (\App\Enums\ServiceTicket\TicketSeverity::cases() as $severity)
                        <option value="{{ $severity->value }}" @selected(($ticket->urgency?->value ?? 2) === $severity->value)>{{ $severity->label() }}</option>
                    @endforeach
                </x-select-field>
                <x-select-field name="priority_override" id="ticket-priority-override" :label="__('Priorität übersteuern')">
                    <option value="">{{ __('aus Auswirkung und Dringlichkeit') }}</option>
                    @foreach (\App\Enums\ServiceTicket\ServiceTicketPriority::cases() as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </x-select-field>
                @if ($services->isNotEmpty())
                    @include('service-tickets._service_fields', ['selectedService' => $ticket->businessService?->sqid, 'selectedOffering' => $ticket->serviceOffering?->sqid])
                @endif
                <x-textarea-field name="workaround" id="ticket-workaround" :label="__('Workaround')" rows="2" span="3" :value="$ticket->workaround" />
                <div class="md:col-span-3">
                    <x-button tone="primary" size="sm" type="submit">{{ __('Klassifizieren') }}</x-button>
                </div>
            </form>
        </x-card>

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
