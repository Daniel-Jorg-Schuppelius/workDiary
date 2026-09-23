{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tariff_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Tarif anlegen/bearbeiten (in #entry-modal geladen). Variablen: $tariff|null, $kinds --}}
<x-modal
    :title="$tariff ? __('club.action.edit') : __('club.fees.action.create_tariff')"
    :eyebrow="__('club.fees.title.tariffs')"
    icon="payments"
    tone="primary"
    :action="$tariff ? route('club.fees.tariffs.update', $tariff) : route('club.fees.tariffs.store')"
    :method="$tariff ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.field.tariff')" icon="payments" tone="primary" cols="3" :description="__('club.fees.hint.tariff')">
        <x-input-field name="name" :label="__('club.fees.field.name')" required maxlength="120" span="2" :value="old('name', $tariff?->name)" />
        <x-select-field name="kind" :label="__('club.fees.field.kind')" required :hint="__('club.fees.hint.kind')">
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $tariff?->kind->value ?? 'individual') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="min_age" type="number" min="0" max="120" :label="__('club.field.min_age')" :value="old('min_age', $tariff?->min_age)" :hint="__('club.fees.hint.age')" />
        <x-input-field name="max_age" type="number" min="0" max="120" :label="__('club.field.max_age')" :value="old('max_age', $tariff?->max_age)" />
        <x-input-field name="sort_order" type="number" min="0" max="9999" :label="__('club.field.sort_order')" :value="old('sort_order', $tariff?->sort_order ?? 0)" />
        <x-textarea-field name="description" :label="__('club.field.description')" rows="2" maxlength="2000" span="3" :value="old('description', $tariff?->description)" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $tariff?->is_active ?? true)" span="3" />
    </x-form-group>
</x-modal>
