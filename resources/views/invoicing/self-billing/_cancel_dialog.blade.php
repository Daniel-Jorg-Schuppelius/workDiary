{{--
  Created on   : Thu Oct 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cancel_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Gutschrift stornieren (MVP-1280) — nur solange die Buchhaltung sie nicht
  übernommen hat. Variablen: $credit.
--}}
<x-modal
    :title="__('invoicing.self_billing.action.cancel')"
    :eyebrow="$credit->number"
    icon="block"
    tone="error"
    :action="route('invoices.self-billing.cancel', $credit)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('invoicing.self_billing.action.cancel')">

    <p class="text-sm">{{ __('invoicing.self_billing.help.cancel') }}</p>
    <x-textarea-field name="reason" :label="__('invoicing.self_billing.field.cancel_reason')" required minlength="2" maxlength="255" rows="2" :value="old('reason')" />
</x-modal>
