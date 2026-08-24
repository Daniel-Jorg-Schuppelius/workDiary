{{--
  Created on   : Wed Jul 08 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _service_tickets_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
    SLA-Status der aus diesem Auftrag angelegten Service-Tickets (Feature 010 →
    Rang 42). Nur sichtbar, wenn Tickets verknüpft sind.
    Erwartet: $diary (DiaryEntry).
--}}
@php
    /** @var \App\Models\DiaryEntry $diary */
    $serviceTickets = $diary->serviceTickets;
@endphp
@if ($serviceTickets->isNotEmpty())
    <x-card>
        <h3 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('sla.diary_panel_heading') }}</h3>
        <x-table>
            <x-slot:head>
                    <tr>
                        <th>{{ __('Ticket') }}</th>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('SLA') }}</th>
                    </tr>
            </x-slot:head>
                    @foreach ($serviceTickets as $ticket)
                        @php $sla = $ticket->slaStatus(); @endphp
                        <tr>
                            <td class="font-mono text-xs">
                                <a href="{{ route('service-tickets.show', $ticket) }}" class="link">{{ $ticket->ticket_no }}</a>
                            </td>
                            <td>{{ $ticket->title }}</td>
                            <td><x-status-badge size="sm" outline>{{ $ticket->status->label() }}</x-status-badge></td>
                            <td><x-status-badge :tone="$sla->tone()" size="sm" outline>{{ $sla->label() }}</x-status-badge></td>
                        </tr>
                    @endforeach
        </x-table>
    </x-card>
@endif
