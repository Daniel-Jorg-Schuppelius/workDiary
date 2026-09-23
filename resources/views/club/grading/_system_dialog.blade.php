{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _system_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ordnung anlegen/bearbeiten (in #entry-modal geladen). Variablen: $system|null --}}
<x-modal
    :title="$system ? __('club.action.edit') : __('club.grading.action.create_system')"
    :eyebrow="__('club.grading.title.index')"
    icon="military_tech"
    tone="primary"
    :action="$system ? route('club.grading.update', $system) : route('club.grading.store')"
    :method="$system ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.grading.card.system')" icon="military_tech" tone="primary" cols="2" :description="__('club.grading.hint.system')">
        <x-input-field name="name" :label="__('club.grading.field.name')" required maxlength="120" :value="old('name', $system?->name)" />
        <x-input-field name="discipline" :label="__('club.field.discipline')" required maxlength="60" :value="old('discipline', $system?->discipline)" :hint="__('club.grading.hint.discipline')" />
        <x-textarea-field name="description" :label="__('club.field.description')" rows="3" maxlength="2000" span="2" :value="old('description', $system?->description)" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $system?->is_active ?? true)" span="2" />
    </x-form-group>
</x-modal>
