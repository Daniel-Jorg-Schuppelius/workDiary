{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _push_all.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sammelübertragung der Kundenliste (Slot `customer-index.actions`, MVP-1038).
--}}
<x-action-form :action="route('customers.lexoffice.push-all')"
      :confirm="__('Alle nicht synchronisierten Kunden zu Lexoffice übertragen?')"
      confirm-icon="sync"
      confirm-tone="info"
      :confirm-label="__('Synchronisieren')">
    <x-icon-btn icon="sync" type="submit" size="sm"
                show-label>{{ __('Lexoffice: alle pushen') }}</x-icon-btn>
</x-action-form>
