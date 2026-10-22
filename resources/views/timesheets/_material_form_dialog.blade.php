{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _material_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Materialzeile am Stundenzettel erfassen oder bearbeiten --}}
@php($usage ??= null)
<x-modal
    :title="$usage ? __('Material bearbeiten') : __('Material erfassen')"
    :eyebrow="__('Verbrauchsmaterial')"
    icon="category"
    tone="primary"
    :action="$usage ? route('projects.timesheets.materials.update', [$project, $timesheet, $usage]) : route('projects.timesheets.materials.store', [$project, $timesheet])"
    :method="$usage ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$usage ? __('Speichern') : __('Hinzufügen')"
>
    <x-form-group :legend="__('Material')" icon="category" tone="primary" cols="2">
        <x-select-field span="2" name="material_id" :label="__('Aus Stamm (optional)')">
            <option value="">— {{ __('frei') }} —</option>
            @foreach ($materials as $m)
                <option value="{{ $m->sqid }}" @selected((string) old('material_id', $usage?->material?->sqid) === $m->sqid)
                        data-unit="{{ $m->unit }}"
                        data-price="{{ $m->default_unit_price }}"
                        data-tax="{{ $m->tax_rate }}"
                        data-name="{{ $m->name }}">
                    {{ $m->name }} ({{ $m->unit }})
                </option>
            @endforeach
        </x-select-field>
        <x-input-field span="2" name="description" :label="__('Bezeichnung')" type="text" required maxlength="255" :value="old('description', $usage?->description)" />
        <x-input-field name="quantity" :label="__('Menge')" type="number" :value="old('quantity', $usage?->quantity?->getNumericValue() ?? 1)" required step="0.001" min="0.001" />
        <x-input-field name="unit" :label="__('Einheit')" type="text" :value="old('unit', $usage?->unit ?? 'Stk.')" required maxlength="20" />
        <x-input-field name="unit_price" :label="__('EP netto')" type="number" step="0.0001" min="0" :value="old('unit_price', $usage?->unit_price?->getAmount())" />
        <x-input-field name="tax_rate" :label="__('USt %')" type="number" step="0.01" min="0" max="100" :value="old('tax_rate', $usage?->tax_rate?->getNumericValue())" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
