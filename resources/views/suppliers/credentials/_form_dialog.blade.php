{{--
  Created on   : Thu Aug 20 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Pflichtnachweis hinterlegen (Feature 117, MVP-606).
--}}
<x-modal
    :title="__('procurement.credentials.action.add')"
    :eyebrow="$supplier->displayLabel()"
    icon="verified_user"
    :action="route('suppliers.credentials.store', $supplier)"
    method="POST"
    :submit-label="__('Speichern')"
>
    <div>
        <label class="label" for="cred-type"><span class="label-text">{{ __('procurement.credentials.column.type') }}</span></label>
        <select id="cred-type" name="supplier_credential_type_id" class="select select-bordered w-full">
            @foreach ($types as $type)
                <option value="{{ $type->sqid }}" @selected(old('supplier_credential_type_id') === $type->sqid)>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="issuer" type="text" maxlength="191"
                       :label="__('procurement.credentials.column.issuer')"
                       :value="old('issuer', '')" />
        <x-input-field name="reference" type="text" maxlength="64"
                       :label="__('procurement.credentials.column.reference')"
                       :value="old('reference', '')" />
    </div>

    <x-date-range layout="split" from-name="issued_on" to-name="valid_until"
                  :from-label="__('procurement.credentials.column.issued_on')"
                  :to-label="__('procurement.credentials.column.valid_until')"
                  :from="old('issued_on', '')"
                  :to="old('valid_until', '')" />

    <p class="text-xs text-base-content/60">{{ __('procurement.credentials.valid_until_hint') }}</p>

    <x-input-field name="note" type="text" maxlength="500"
                   :label="__('procurement.credentials.column.note')"
                   :value="old('note', '')" />
</x-modal>
