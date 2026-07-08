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
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
        <h3 class="mb-2 font-['Space_Grotesk'] text-sm font-semibold">{{ __('sla.diary_panel_heading') }}</h3>
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Ticket') }}</th>
                        <th>{{ __('Titel') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('SLA') }}</th>
                    </tr>
                </thead>
                <tbody>
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
                </tbody>
            </table>
        </div>
    </div>
@endif
