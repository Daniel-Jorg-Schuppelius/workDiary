{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Punkt hinzufügen (MVP-883). Auswahlwerte, Einheit und Grenzen
     landen als Konfiguration im value_json des Punkts. --}}
<x-modal
    :title="__('protocol.action.addItem')"
    :eyebrow="$protocol->title"
    icon="playlist_add"
    tone="primary"
    :action="route('protocols.items.store', $protocol)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('protocol.action.addItem')">
    <x-input-field name="label" :label="__('protocol.dialog.label')" :value="old('label')" required maxlength="180" />
    <x-select-field name="item_type" :label="__('protocol.dialog.item_type')" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(old('item_type', \App\Enums\Protocol\ProtocolItemType::Boolean->value) === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-select-field>
    @if ($groups->isNotEmpty())
        <x-select-field name="parent_item_id" :label="__('protocol.dialog.parent')">
            <option value="">—</option>
            @foreach ($groups as $group)
                <option value="{{ $group->sqid }}" @selected(old('parent_item_id') === $group->sqid)>{{ $group->label }}</option>
            @endforeach
        </x-select-field>
    @endif
    <x-checkbox-field name="required" :label="__('protocol.dialog.required')" :checked="(bool) old('required')" />
    <x-textarea-field name="description" :label="__('protocol.field.description')" :value="old('description')" rows="2" />
    <x-textarea-field name="options" :label="__('protocol.dialog.options')" :hint="__('protocol.dialog.options_hint')" :value="old('options')" rows="3" />
    <x-input-field name="unit" :label="__('protocol.dialog.unit')" :value="old('unit')" maxlength="20" />
    <x-input-field name="min" type="number" step="any" :label="__('protocol.dialog.min')" :value="old('min')" />
    <x-input-field name="max" type="number" step="any" :label="__('protocol.dialog.max')" :value="old('max')" />
</x-modal>
