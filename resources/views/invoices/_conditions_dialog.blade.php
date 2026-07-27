{{--
  Created on   : Fri Jul 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _conditions_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- MVP-416: Belegrabatt + Skonto-Kondition am Entwurf. Variablen: $invoice --}}
<x-modal
    :title="__('Konditionen')"
    :eyebrow="$invoice->number"
    icon="percent"
    tone="primary"
    :action="route('invoices.conditions.update', $invoice)"
    method="PATCH"
    :submit-label="__('Speichern')"
    size="md">

    <x-form-group :legend="__('Belegrabatt')" icon="percent" tone="primary" cols="2">
        <x-input-field name="discount_percent" type="number" :label="__('Rabatt %')" min="0" max="100" step="0.01"
                       :value="old('discount_percent', $invoice->discount_percent?->getNumericValue() ?? '')"
                       :hint="__('Prozent oder Betrag — nicht beides.')" />
        <x-input-field name="discount_amount" type="number" :label="__('Rabatt (Betrag)')" min="0" step="0.01"
                       :value="old('discount_amount', $invoice->discount_amount?->getAmount() ?? '')" />
    </x-form-group>

    <x-form-group :legend="__('Skonto')" icon="schedule" tone="ghost" cols="2">
        <x-input-field name="skonto_percent" type="number" :label="__('Skonto %')" min="0.01" max="100" step="0.01"
                       :value="old('skonto_percent', $invoice->skonto_percent?->getNumericValue() ?? '')" />
        <x-input-field name="skonto_days" type="number" :label="__('Skonto-Frist (Tage)')" min="1" max="365" step="1"
                       :value="old('skonto_days', $invoice->skonto_days ?? '')"
                       :hint="__('Wird auf PDF und E-Rechnung ausgewiesen und beim Zahlungsabgleich berücksichtigt.')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
