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
    $now = now();
    $resDue = $ticket->resolution_due_at;
    $reactDue = $ticket->reaction_due_at;
    $slaPill = match (true) {
        $ticket->resolution_breached => ['text-error', __('SLA verletzt')],
        $resDue !== null && $resDue->lessThan($now) => ['text-error', __('SLA verletzt')],
        $resDue !== null && $resDue->lessThan($now->copy()->addHours(4)) => ['text-warning', __('SLA kritisch')],
        $resDue !== null => ['text-success', __('SLA im Plan')],
        default => ['text-base-content/60', __('Kein SLA')],
    };
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
            <span class="ml-auto {{ $slaPill[0] }} font-medium">
                <span class="material-symbols-outlined align-middle text-[16px]">timer</span>
                {{ $slaPill[1] }}
                @if ($resDue)
                    · {{ __('Lösung bis :date', ['date' => $resDue->translatedFormat('d.m.Y H:i')]) }}
                @endif
            </span>
        </div>

        <div class="divider my-3"></div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <div><dt class="text-base-content/60">{{ __('Gemeldet von') }}</dt><dd>{{ $ticket->reportedBy?->name ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Gemeldet am') }}</dt><dd>{{ $ticket->reported_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Bearbeiter') }}</dt><dd>{{ $ticket->assignedTo?->name ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Bestätigt') }}</dt><dd>{{ $ticket->acknowledged_at?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Reaktion bis') }}</dt><dd>{{ $reactDue?->translatedFormat('d.m.Y H:i') ?: '—' }}</dd></div>
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
                <button class="btn btn-primary btn-sm" type="submit">{{ __('Übernehmen') }}</button>
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
</x-page-shell>
@endsection
