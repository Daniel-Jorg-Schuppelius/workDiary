{{--
  Created on   : Sun Aug 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tax_code_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kennziffern eines Steuerkennzeichens (Feature 125, MVP-688). Sie ordnen die
  Zeile den Feldern der Voranmeldung zu — eine Abgleichhilfe, kein Vordruck.
--}}
<x-modal
    :title="$taxCode->code . ' — ' . $taxCode->name"
    icon="tag"
    :action="route('finance.accounting.tax-codes.update', $taxCode)"
    method="PUT"
    :submit-label="__('Speichern')"
>
    <p class="text-sm text-base-content/70">
        {{ $taxCode->direction->label() }} · {{ $taxCode->rate }} %
    </p>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="ustva_base_field" type="text" maxlength="8"
                       :label="__('accounting.filing.fields.column.base')"
                       :hint="__('accounting.filing.fields.hint.base')"
                       :value="old('ustva_base_field', $taxCode->ustva_base_field)" />
        <x-input-field name="ustva_tax_field" type="text" maxlength="8"
                       :label="__('accounting.filing.fields.column.tax')"
                       :hint="__('accounting.filing.fields.hint.tax')"
                       :value="old('ustva_tax_field', $taxCode->ustva_tax_field)" />
    </div>
</x-modal>
