{{--
  Created on   : Sun May 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _material_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog for adding a MaterialUsage row to a Timesheet --}}
<x-modal
    :title="__('Material erfassen')"
    :eyebrow="__('Verbrauchsmaterial')"
    icon="category"
    tone="primary"
    :action="route('projects.timesheets.materials.store', [$project, $timesheet])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Hinzufügen')"
>
    <x-form-group :legend="__('Material')" icon="category" tone="primary" cols="2">
        <x-select-field span="2" name="material_id" :label="__('Aus Stamm (optional)')">
            <option value="">— {{ __('frei') }} —</option>
            @foreach ($materials as $m)
                <option value="{{ $m->sqid }}" @selected((string) old('material_id') === $m->sqid)
                        data-unit="{{ $m->unit }}"
                        data-price="{{ $m->default_unit_price }}"
                        data-tax="{{ $m->tax_rate }}"
                        data-name="{{ $m->name }}">
                    {{ $m->name }} ({{ $m->unit }})
                </option>
            @endforeach
        </x-select-field>
        <x-input-field span="2" name="description" :label="__('Bezeichnung')" type="text" required maxlength="255" />
        <x-input-field name="quantity" :label="__('Menge')" type="number" value="1" required step="0.001" min="0.001" />
        <x-input-field name="unit" :label="__('Einheit')" type="text" value="Stk." required maxlength="20" />
        <x-input-field name="unit_price" :label="__('EP netto')" type="number" step="0.0001" min="0" />
        <x-input-field name="tax_rate" :label="__('USt %')" type="number" step="0.01" min="0" max="100" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
