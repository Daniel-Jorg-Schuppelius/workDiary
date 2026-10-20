{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

{{-- Portal-Ticketdetail (Feature 065, MVP-160): nur public-Inhalte —
     Leak-Schutz strukturell über ServiceTicketTimelineService::forCustomer()
     (MVP-152); SLA-Zusage aus dem eingefrorenen Snapshot; bestätigen/
     wiedereröffnen/bewerten nach Abschluss. --}}

@section('content')
    <x-page-toolbar :title="$ticket->title"
                    :subtitle="$ticket->ticket_no . ($ticket->resolution_due_at ? ' · ' . __('Lösung zugesagt bis :date', ['date' => $ticket->resolution_due_at->orgTz()->isoFormat('L LT')]) : '')"
                    :back="route('customer.tickets.index')" :back-label="__('Meine Tickets')" class="mb-4">
        <x-slot:badges>
            <x-status-badge size="sm" :tone="$ticket->status->isResolved() ? 'success' : 'info'" outline>{{ $ticket->status->label() }}</x-status-badge>
        </x-slot:badges>
    </x-page-toolbar>

    <div class="space-y-2">
        @forelse ($timeline['items'] as $item)
            <x-card padding="px-3 py-2">
                <p class="mb-1 text-xs text-muted">
                    {{ $item->occurredAt?->isoFormat('L LT') ?? '—' }} · {{ $item->title }}
                    @if ($item->actor)
                        · {{ $item->actor }}
                    @endif
                </p>
                @if ($item->type === 'attachment' && $item->url)
                    {{-- Anhang als Download-Link (W5.1, kunden-gescopter Endpunkt). --}}
                    <a href="{{ $item->url }}" class="link link-hover inline-flex items-center gap-1 text-sm">
                        <x-icon name="download" class="text-sm" />
                        {{ $item->summary }}
                    </a>
                @elseif ($item->summary)
                    <p class="whitespace-pre-line text-sm">{{ $item->summary }}</p>
                @endif
            </x-card>
        @empty
            <x-empty-state framed icon="forum" :title="__('Noch keine Nachrichten.')" compact />
        @endforelse
    </div>

    <form method="POST" action="{{ route('customer.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="mt-4">
        @csrf
        <textarea aria-label="{{ __('Ihre Antwort…') }}" name="body" rows="3" required minlength="2" maxlength="10000" class="textarea textarea-bordered w-full" placeholder="{{ __('Ihre Antwort…') }}"></textarea>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <input name="files[]" type="file" multiple class="file-input file-input-sm file-input-bordered"
                   aria-label="{{ __('Anhänge (optional)') }}">
            <x-button type="submit">{{ __('Antworten') }}</x-button>
        </div>
        @error('files')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        @error('files.*')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </form>

    @if ($canReopen)
        <div class="mt-4 flex flex-wrap items-start gap-3 rounded-box border border-info/40 bg-info/5 p-3">
            @if ($ticket->status === \App\Enums\ServiceTicket\ServiceTicketStatus::Done)
                <form method="POST" action="{{ route('customer.tickets.accept', $ticket) }}">
                    @csrf
                    <x-button type="submit" tone="success">{{ __('Lösung bestätigen') }}</x-button>
                </form>
            @endif
            <form method="POST" action="{{ route('customer.tickets.reopen', $ticket) }}" class="flex items-center gap-2">
                @csrf
                <input aria-label="{{ __('Grund der Wiedereröffnung') }}" name="reason" required minlength="5" maxlength="500" class="input input-sm input-bordered" placeholder="{{ __('Grund der Wiedereröffnung') }}">
                <x-button type="submit" tone="outline">{{ __('Wiedereröffnen') }}</x-button>
            </form>
        </div>
    @endif

    @if ($ticket->status->isResolved() && ! $rated)
        <form method="POST" action="{{ route('customer.tickets.rate', $ticket) }}" class="mt-4 rounded-box border border-base-300 p-3">
            @csrf
            <p class="mb-1 text-sm font-semibold">{{ __('Wie zufrieden waren Sie mit der Bearbeitung?') }}</p>
            <div class="flex items-center gap-2">
                <select name="score" class="select select-sm select-bordered">
                    @foreach ([5 => __('Sehr zufrieden'), 4 => __('Zufrieden'), 3 => __('Neutral'), 2 => __('Unzufrieden'), 1 => __('Sehr unzufrieden')] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input aria-label="{{ __('Anmerkung (optional)') }}" name="comment" maxlength="500" class="input input-sm input-bordered flex-1" placeholder="{{ __('Anmerkung (optional)') }}">
                <x-button type="submit">{{ __('Bewerten') }}</x-button>
            </div>
        </form>
    @endif
@endsection
