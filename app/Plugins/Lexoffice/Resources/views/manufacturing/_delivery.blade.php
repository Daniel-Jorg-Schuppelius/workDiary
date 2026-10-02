{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _delivery.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lieferschein an Lexoffice je Auslieferung (Slot `manufacturing-delivery.actions`,
  MVP-1040). Erwartet: $delivery, $orderKey, $canPush.
--}}
@if ($canPush && in_array($delivery->facturation_status->value, ['pending', 'failed'], true))
    <form method="POST" action="{{ route('manufacturing-orders.deliveries.lexoffice', [$orderKey, $delivery]) }}">@csrf
        <button type="submit" class="btn btn-xs">{{ __('lexoffice::manufacturing.order.action.push_lexoffice') }}</button>
    </form>
@elseif ($delivery->facturation_status->value === 'handed_over' && $delivery->external_id)
    <span class="text-xs text-muted">Lexoffice: {{ $delivery->external_id }}</span>
@endif
