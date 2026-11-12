{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Interne Katalogbestellung (MVP-1549): eigene Bestellungen; bestellt wird im Dialog.
--}}

@extends('layouts.app')
@section('title', __('helpdesk.order.title'))
@section('nav-title', __('helpdesk.order.title'))
@include('partials.page-fill')

@section('content')
    <x-index-page overflow="clip" :subtitle="__('helpdesk.order.subtitle')">
        <x-slot:actions>
            <x-icon-btn icon="shopping_cart" tone="primary" size="sm"
                        data-entry-modal-trigger
                        :href="route('servicedesk.order.pick')"
                        show-label>{{ __('helpdesk.order.title') }}</x-icon-btn>
        </x-slot:actions>

        <x-table scroll="flex" :zebra="true">
            <x-slot:head>
                <tr>
                    <th>{{ __('Nummer') }}</th>
                    <th>{{ __('Leistung') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Bestellt am') }}</th>
                </tr>
            </x-slot:head>
            @forelse ($requests as $request)
                <tr class="hover">
                    <td class="whitespace-nowrap font-mono text-sm">
                        @if ($request->ticket !== null && Gate::allows('view', $request->ticket))
                            <a class="link link-hover" href="{{ route('service-tickets.show', $request->ticket) }}">{{ $request->ticket->ticket_no }}</a>
                        @else
                            {{ $request->ticket?->ticket_no ?? '—' }}
                        @endif
                    </td>
                    <td>{{ $request->catalog_snapshot['name'] ?? $request->requestItem?->name ?? '—' }}</td>
                    {{-- „Erfüllt“ heißt beim Request nur „Zielvorgang angelegt“ — maßgeblich ist danach das Ticket. --}}
                    <td><x-status-badge size="sm" outline>{{ in_array($request->status, [\App\Enums\ServiceTicket\ServiceRequestStatus::PendingApproval, \App\Enums\ServiceTicket\ServiceRequestStatus::Rejected], true) || $request->ticket === null ? $request->status->label() : $request->ticket->status->label() }}</x-status-badge></td>
                    <td class="whitespace-nowrap">{{ $request->created_at?->orgTz()->isoFormat('L') }}</td>
                </tr>
            @empty
                <x-table.empty :colspan="4" icon="shopping_cart" :title="__('Noch keine Bestellungen vorhanden.')" compact />
            @endforelse
        </x-table>

        <x-pagination :paginator="$requests" standing />
    </x-index-page>
@endsection
