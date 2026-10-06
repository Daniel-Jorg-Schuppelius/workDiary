{{--
  Created on   : Mon Jul 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@extends('customer.layout')

{{-- Portal-Servicekatalog (Feature 065, MVP-154): nur portal-sichtbare
     Einträge; darunter der Status der eigenen Bestellungen. --}}

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Servicekatalog') }}</h1>
    </div>

    @if ($items->isEmpty())
        <x-card padding="p-6" class="text-center text-muted">
            {{ __('Derzeit sind keine Leistungen bestellbar.') }}
        </x-card>
    @else
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($items as $item)
                <x-card>
                    <div class="font-semibold">{{ $item->name }}</div>
                    @if ($item->description)
                        <p class="mt-1 text-sm text-muted">{{ $item->description }}</p>
                    @endif
                    <x-button :href="route('customer.catalog.show', $item)" class="mt-3">
                        {{ __('Bestellen') }}
                    </x-button>
                </x-card>
            @endforeach
        </div>
    @endif

    <h2 class="mt-8 mb-2 text-lg font-semibold">{{ __('Meine Bestellungen') }}</h2>
    <x-table>
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('Nummer') }}</x-table.th>
                <x-table.th>{{ __('Leistung') }}</x-table.th>
                <x-table.th>{{ __('Status') }}</x-table.th>
                <x-table.th>{{ __('Bestellt am') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($requests as $request)
            <tr>
                <td class="whitespace-nowrap font-mono text-sm">
                    @if ($request->ticket !== null)
                        <a class="link link-hover" href="{{ route('customer.tickets.show', $request->ticket) }}">{{ $request->ticket->ticket_no }}</a>
                    @else
                        —
                    @endif
                </td>
                <td>{{ $request->catalog_snapshot['name'] ?? $request->requestItem?->name ?? '—' }}</td>
                <td>{{ $request->status->label() }}</td>
                <td class="whitespace-nowrap">{{ $request->created_at?->isoFormat('L') }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" :title="__('Noch keine Bestellungen vorhanden.')" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$requests" standing />
@endsection
