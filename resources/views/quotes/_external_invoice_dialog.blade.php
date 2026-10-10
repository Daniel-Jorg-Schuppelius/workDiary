{{--
  Created on   : Sat Oct 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _external_invoice_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Angebot als im Buchhaltungsprogramm abgerechnet markieren (MVP-1124).
--}}
<x-modal
    :title="__('quotes.external_invoice.title', ['number' => $quote->number])"
    icon="receipt_long"
    :action="route('quotes.external-invoice.store', $quote)"
    method="POST"
    :submit-label="__('quotes.external_invoice.submit')"
>
    <p class="text-sm text-base-content/70">{{ __('quotes.external_invoice.hint') }}</p>

    <x-input-field name="external_invoice_number" maxlength="64"
                   :label="__('quotes.external_invoice.number')"
                   :value="old('external_invoice_number', $quote->external_invoice_number)" />
</x-modal>
