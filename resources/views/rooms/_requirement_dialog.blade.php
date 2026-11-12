{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _requirement_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Raumanforderung ändern (Feature 027). Variablen: $room, $requirement, $requirementKinds --}}
<x-modal
    :title="__('Raumanforderung bearbeiten')"
    :eyebrow="$room->name"
    icon="rule"
    tone="warning"
    :action="route('rooms.requirements.update', [$room, $requirement])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <x-slot:headerActions>
        <x-dialog-status-controls name="is_active" :active="$requirement->is_active" />
    </x-slot:headerActions>

    <x-form-group :legend="__('Anforderung')" icon="rule" tone="warning" cols="2">
        <x-select-field name="kind" :label="__('Anforderung')" required>
            @foreach ($requirementKinds as $value => $label)
                <option value="{{ $value }}" @selected(old('kind', $requirement->kind->value) === $value)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="level" :label="__('Stufe / Wert')" maxlength="60" :value="old('level', $requirement->level)" />
        <x-input-field name="note" :label="__('Notiz')" maxlength="2000" span="2" :value="old('note', $requirement->note)" />
    </x-form-group>
</x-modal>
