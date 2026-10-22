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
    <x-page-toolbar :title="__('Meine Tickets')" :subtitle="__('customer_portal.nav.tickets')" class="mb-4" />

    <x-card as="details" padding="p-3" class="mb-4">
        <summary class="cursor-pointer text-sm font-semibold">{{ __('Neues Ticket melden') }}</summary>
        <form method="POST" action="{{ route('customer.tickets.store') }}" enctype="multipart/form-data" class="mt-2 space-y-2">
            @csrf
            <input aria-label="{{ __('Kurzbeschreibung') }}" name="title" required minlength="3" maxlength="255" class="input input-bordered w-full" placeholder="{{ __('Kurzbeschreibung') }}">
            <textarea aria-label="{{ __('Was ist passiert?') }}" name="description" rows="3" maxlength="10000" class="textarea textarea-bordered w-full" placeholder="{{ __('Was ist passiert?') }}"></textarea>
            @if ($assets->isNotEmpty() || $offerings->isNotEmpty())
                <div class="grid gap-2 sm:grid-cols-2">
                    @if ($assets->isNotEmpty())
                        <x-select-field name="asset" id="portal-ticket-asset" :label="__('helpdesk.portal.asset')">
                            <option value="">{{ __('helpdesk.portal.noAsset') }}</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->sqid }}" @selected(old('asset') === $asset->sqid)>{{ $asset->name }}</option>
                            @endforeach
                        </x-select-field>
                    @endif
                    @if ($offerings->isNotEmpty())
                        <x-select-field name="offering" id="portal-ticket-offering" :label="__('helpdesk.portal.service')">
                            <option value="">{{ __('helpdesk.portal.noService') }}</option>
                            @foreach ($offerings as $offering)
                                <option value="{{ $offering->sqid }}" @selected(old('offering') === $offering->sqid)>{{ $offering->name }}</option>
                            @endforeach
                        </x-select-field>
                    @endif
                </div>
            @endif
            <div class="flex flex-wrap items-center gap-2">
                <input name="files[]" type="file" multiple class="file-input file-input-sm file-input-bordered"
                       aria-label="{{ __('Anhänge (optional)') }}">
                <x-button type="submit">{{ __('Ticket anlegen') }}</x-button>
            </div>
            @error('files')<p class="text-error text-xs">{{ $message }}</p>@enderror
            @error('files.*')<p class="text-error text-xs">{{ $message }}</p>@enderror
        </form>
    </x-card>

    <x-card>
    <x-table bare>
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
                <td><x-status-badge size="sm" :tone="$ticket->status->isResolved() ? 'success' : 'info'" outline>{{ $ticket->status->label() }}</x-status-badge></td>
                <td class="whitespace-nowrap">{{ $ticket->reported_at?->isoFormat('L') }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" icon="confirmation_number" :title="__('Keine Tickets vorhanden.')" compact />
        @endforelse
    </x-table>
    </x-card>

    <x-pagination :paginator="$tickets" standing />
@endsection
