{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _customer_approval_state.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Stand der Kunden-Druckfreigabe (MVP-1076) — erwartet: $order (PrintOrder). --}}
@if ($order->customerApprovalMatchesFile())
    <x-status-badge size="sm" outline tone="success">{{ __('print.intake.approval.approved', ['date' => $order->customer_approved_at?->fdatetime()]) }}</x-status-badge>
@elseif ($order->customerApprovalPending())
    <x-status-badge size="sm" outline tone="warning">{{ __('print.intake.approval.pending', ['date' => $order->customer_approval_requested_at?->fdatetime()]) }}</x-status-badge>
@elseif ($order->customer_declined_at !== null)
    <x-status-badge size="sm" outline tone="error">{{ __('print.intake.approval.declined', ['date' => $order->customer_declined_at->fdatetime()]) }}</x-status-badge>
    @if ($order->customer_decline_reason !== null)
        <span class="block text-sm">{{ $order->customer_decline_reason }}</span>
    @endif
@else
    <x-status-badge size="sm" outline>{{ __('print.intake.approval.none') }}</x-status-badge>
@endif
