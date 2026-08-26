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
    <h1 class="text-2xl font-semibold mb-1">{{ $ticket->title }}</h1>
    <p class="mb-4 text-sm text-muted">
        {{ $ticket->ticket_no }} · {{ $ticket->status->label() }}
        @if ($ticket->resolution_due_at)
            · {{ __('Lösung zugesagt bis :date', ['date' => $ticket->resolution_due_at->isoFormat('L LT')]) }}
        @endif
    </p>

    <div class="space-y-2">
        @forelse ($timeline['items'] as $item)
            <div class="rounded border border-base-300 bg-base-100 px-3 py-2">
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
            </div>
        @empty
            <p class="text-sm text-muted">{{ __('Noch keine Nachrichten.') }}</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('customer.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="mt-4">
        @csrf
        <textarea name="body" rows="3" required minlength="2" maxlength="10000" class="textarea textarea-bordered w-full" placeholder="{{ __('Ihre Antwort…') }}"></textarea>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <input name="files[]" type="file" multiple class="file-input file-input-sm file-input-bordered"
                   aria-label="{{ __('Anhänge (optional)') }}">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Antworten') }}</button>
        </div>
        @error('files')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
        @error('files.*')<p class="text-error text-xs mt-1">{{ $message }}</p>@enderror
    </form>

    @if ($ticket->status->value === 'done')
        <div class="mt-4 flex flex-wrap items-start gap-3 rounded-box border border-info/40 bg-info/5 p-3">
            <form method="POST" action="{{ route('customer.tickets.accept', $ticket) }}">
                @csrf
                <button type="submit" class="btn btn-success btn-sm">{{ __('Lösung bestätigen') }}</button>
            </form>
            <form method="POST" action="{{ route('customer.tickets.reopen', $ticket) }}" class="flex items-center gap-2">
                @csrf
                <input name="reason" required minlength="5" maxlength="500" class="input input-sm input-bordered" placeholder="{{ __('Grund der Wiedereröffnung') }}">
                <button type="submit" class="btn btn-outline btn-sm">{{ __('Wiedereröffnen') }}</button>
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
                <input name="comment" maxlength="500" class="input input-sm input-bordered flex-1" placeholder="{{ __('Anmerkung (optional)') }}">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Bewerten') }}</button>
            </div>
        </form>
    @endif
@endsection
