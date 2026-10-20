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
    <x-page-toolbar :title="__('Servicekatalog')" :subtitle="__('customer_portal.nav.catalog')" class="mb-4" />

    @if ($items->isEmpty())
        <x-empty-state framed icon="storefront" :title="__('Derzeit sind keine Leistungen bestellbar.')" />
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

    <x-card :title="__('Meine Bestellungen')" icon="shopping_cart" padding="p-0" class="mt-8">
    <x-table bare>
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
                {{-- „Erfüllt" heißt beim Request nur „Zielvorgang angelegt" (MVP-1077) — maßgeblich ist das Ticket. --}}
                <td><x-status-badge size="sm" outline>{{ in_array($request->status, [\App\Enums\ServiceTicket\ServiceRequestStatus::PendingApproval, \App\Enums\ServiceTicket\ServiceRequestStatus::Rejected], true) || $request->ticket === null ? $request->status->label() : $request->ticket->status->label() }}</x-status-badge></td>
                <td class="whitespace-nowrap">{{ $request->created_at?->isoFormat('L') }}</td>
            </tr>
        @empty
            <x-table.empty :colspan="4" icon="shopping_cart" :title="__('Noch keine Bestellungen vorhanden.')" compact />
        @endforelse
    </x-table>
    </x-card>

    <x-pagination :paginator="$requests" standing />
@endsection
