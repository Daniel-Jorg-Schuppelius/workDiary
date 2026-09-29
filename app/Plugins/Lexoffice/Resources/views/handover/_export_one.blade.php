{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _export_one.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einzelexport an Lexware im Export-Menü der Rechnung (Slot
  `invoice-show.exports`, MVP-1039). Erwartet: $invoice.
--}}
<x-icon-btn icon="outbox" size="sm" :href="route('lexoffice.handover.export-one', $invoice)" show-label :title="__('lexware.action.export_one')">{{ __('lexware.action.export_one_short') }}</x-icon-btn>
