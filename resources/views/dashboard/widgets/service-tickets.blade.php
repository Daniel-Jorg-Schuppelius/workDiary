{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : service-tickets.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Meine Tickets" — Daten: ServiceTicketsWidget.
--}}
<x-card :title="__('Meine Tickets')" icon="confirmation_number" :count="$openCount">
    <x-slot:actions>
        <x-button href="{{ route('service-tickets.index') }}" tone="ghost" size="xs">{{ __('Alle →') }}</x-button>
    </x-slot:actions>

    @if ($tickets->isEmpty())
        <x-empty-state compact icon="confirmation_number"
                       :title="__('Keine offenen Tickets')" :message="__('Dir ist derzeit kein offenes Ticket zugewiesen.')" />
    @else
        <ul class="space-y-2 text-sm">
            @foreach ($tickets as $ticket)
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-box border border-base-300 bg-base-200 px-3 py-2">
                    <a href="{{ route('service-tickets.show', $ticket) }}" class="link link-primary min-w-0 truncate">
                        {{ $ticket->ticket_no ? $ticket->ticket_no . ' · ' : '' }}{{ \CommonToolkit\Helper\Data\StringHelper::truncate($ticket->title, 50) }}
                    </a>
                    <x-status-badge size="xs" :tone="$ticket->status->isWaiting() ? 'warning' : 'info'">{{ $ticket->status->label() }}</x-status-badge>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
