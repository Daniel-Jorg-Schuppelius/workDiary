{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _occasion_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Vorsorgeanlass anlegen/bearbeiten (MVP-986). Variablen: $occasion (MedicalCheckupOccasion|null)
--}}
@php $isEdit = $occasion !== null; @endphp
<x-modal
    :title="$isEdit ? __('safety.register.action.edit_occasion') : __('safety.register.action.create_occasion')"
    :eyebrow="__('safety.register.title.occasions')"
    icon="event_repeat"
    tone="primary"
    :action="$isEdit ? route('safety.checkups.occasions.update', $occasion) : route('safety.checkups.occasions.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('safety.register.action.save')">
    <x-input-field name="label" :label="__('safety.register.field.occasion')" required maxlength="180" :value="old('label', $occasion?->label)" />
    <x-select-field name="kind" :label="__('safety.register.field.kind')" required>
        @foreach (\App\Enums\Safety\MedicalCheckupKind::cases() as $k)
            <option value="{{ $k->value }}" @selected(old('kind', $occasion?->kind?->value ?? 'offered') === $k->value)>{{ $k->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="interval_months" type="number" min="1" max="120" step="1" inputmode="numeric"
                   :label="__('safety.register.field.interval_months')" :hint="__('safety.register.hint.occasion_interval')"
                   :value="old('interval_months', $occasion?->interval_months)" />
    @if ($isEdit)
        <x-checkbox-field name="is_active" :label="__('safety.register.field.is_active')" :checked="(bool) old('is_active', $occasion->is_active)" />
    @endif
</x-modal>
