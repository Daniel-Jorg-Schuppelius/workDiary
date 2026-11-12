{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _retention_release_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Einbehalt freigeben (MVP-1560, in #entry-modal geladen). Variablen: $invoice, $retention --}}
@php
    $secured = $retention->status === \App\Enums\Invoicing\RetentionStatus::Secured;
    $amount = \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($retention->amount->toFloat(), 2, withThousandsSeparator: true) . ' ' . $invoice->currency->value;
@endphp
<x-modal
    :title="__('invoicing.retention.release_title')"
    :eyebrow="(string) $invoice->number"
    icon="lock_open"
    :action="route('invoices.retentions.release', [$invoice, $retention])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('invoicing.retention.release')">

    <x-form-group :legend="$retention->kind->label() . ' · ' . $amount" icon="savings" cols="1"
                  :description="$secured ? __('invoicing.retention.release_hint_secured') : __('invoicing.retention.release_hint')">
        <x-input-field name="note" type="text" maxlength="500"
                       :label="__('invoicing.retention.note')"
                       :value="old('note', (string) $retention->note)" />
    </x-form-group>
</x-modal>
