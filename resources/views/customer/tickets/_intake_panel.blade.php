{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _intake_panel.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Übernommener IT-Eingang im Portal (MVP-1077) — erwartet: $intake, $target (ServiceTicket).
  Gespräch und Lösung laufen im Ticket; Link nur mit Portalfreigabe „Tickets".
--}}
@php
    $portalCustomer = auth('customer')->user()?->customer;
    $ticketsAllowed = $portalCustomer !== null && app(\App\Services\CustomerPortal\PortalVisibility::class)->allows($portalCustomer, \App\Enums\CustomerPortal\PortalCapability::Tickets);
@endphp
<x-card :title="__('customer_intake.it_target.portal_title')">
    <p class="text-sm">
        {{ __('customer_intake.it_target.portal_text', ['number' => $target->ticket_no]) }}
    </p>
    @if ($ticketsAllowed)
        <x-button class="mt-3" size="sm" icon="open_in_new" :href="route('customer.tickets.show', $target)">{{ __('customer_intake.it_target.open_ticket') }}</x-button>
    @endif
</x-card>
