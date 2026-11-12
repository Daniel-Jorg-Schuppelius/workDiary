{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _boq_progress_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Fortschritt zu einer LV-Position des Projekts melden (MVP-1582). Erwartet: $protocol, $positions (Schlüssel → Bezeichnung) --}}
<x-modal
    :title="__('protocol.boq_progress.title')"
    :eyebrow="$protocol->title ?: $protocol->type->label()"
    icon="straighten"
    tone="primary"
    :action="route('protocols.boq-progress.store', $protocol)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.boq_progress.submit')">
    <input type="hidden" name="_dialog_url" value="{{ route('protocols.boq-progress.create', $protocol) }}?dialog=1">

    <x-form-group :legend="__('protocol.boq_progress.title')" icon="straighten" tone="primary" cols="2"
                  :description="__('protocol.boq_progress.description')">
        <x-select-field name="position" id="protocol-boq-position" :label="__('protocol.boq_progress.position')" required span="2">
            @foreach ($positions as $key => $label)
                <option value="{{ $key }}" @selected(old('position') === $key)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="quantity" id="protocol-boq-quantity" type="number" step="0.001" required inputmode="decimal"
                       :label="__('protocol.boq_progress.quantity')" :value="old('quantity')" />
        <x-input-field name="note" id="protocol-boq-note" :label="__('protocol.boq_progress.note')" maxlength="500" :value="old('note')" />
    </x-form-group>
</x-modal>
