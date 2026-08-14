{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

{{-- Portal-Tickets (Feature 065, MVP-160): nur eigene Tickets. --}}

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Meine Tickets') }}</h1>
    </div>

    <details class="mb-4 rounded-box border border-base-300 bg-base-100 p-3">
        <summary class="cursor-pointer text-sm font-semibold">{{ __('Neues Ticket melden') }}</summary>
        <form method="POST" action="{{ route('customer.tickets.store') }}" enctype="multipart/form-data" class="mt-2 space-y-2">
            @csrf
            <input name="title" required minlength="3" maxlength="255" class="input input-bordered w-full" placeholder="{{ __('Kurzbeschreibung') }}">
            <textarea name="description" rows="3" maxlength="10000" class="textarea textarea-bordered w-full" placeholder="{{ __('Was ist passiert?') }}"></textarea>
            <div class="flex flex-wrap items-center gap-2">
                <input name="files[]" type="file" multiple class="file-input file-input-sm file-input-bordered"
                       aria-label="{{ __('Anhänge (optional)') }}">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Ticket anlegen') }}</button>
            </div>
            @error('files')<p class="text-error text-xs">{{ $message }}</p>@enderror
            @error('files.*')<p class="text-error text-xs">{{ $message }}</p>@enderror
        </form>
    </details>

    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('Titel') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
                <x-table.th>{{ __('Gemeldet') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($tickets as $ticket)
            <tr>
                <td class="whitespace-nowrap font-mono text-sm">{{ $ticket->ticket_no }}</td>
                <td><a class="link link-hover" href="{{ route('customer.tickets.show', $ticket) }}">{{ $ticket->title }}</a></td>
                <td>{{ $ticket->status->label() }}</td>
                <td class="whitespace-nowrap">{{ $ticket->reported_at?->isoFormat('L') }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Keine Tickets vorhanden.')" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$tickets" standing />
@endsection
