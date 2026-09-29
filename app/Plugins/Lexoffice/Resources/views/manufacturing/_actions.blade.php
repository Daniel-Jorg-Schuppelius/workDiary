{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _actions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Angebot und Auftragsbestätigung an Lexoffice (Slot `manufacturing-show.actions`,
  MVP-1040). Erwartet: $order (mit Kunde).
--}}
@php $status = $order->status->value; @endphp
@if ($status === 'draft')
    <form method="POST" action="{{ route('manufacturing-orders.quotation.lexoffice', $order) }}">@csrf
        <x-icon-btn icon="request_quote" size="sm" type="submit" placement="menu" show-label>{{ __('Angebot an Lexoffice') }}</x-icon-btn>
    </form>
@elseif ($status !== 'cancelled')
    <form method="POST" action="{{ route('manufacturing-orders.order-confirmation.lexoffice', $order) }}">@csrf
        <x-icon-btn icon="sync" size="sm" type="submit" placement="menu" show-label>{{ __('Auftragsbestätigung an Lexoffice') }}</x-icon-btn>
    </form>
@endif
