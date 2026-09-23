{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _grade_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Grad anlegen/bearbeiten (in #entry-modal geladen). Variablen: $system, $grade|null --}}
<x-modal
    :title="$grade ? __('club.action.edit') : __('club.grading.action.create_grade')"
    :eyebrow="$system->name"
    icon="military_tech"
    tone="primary"
    :action="$grade ? route('club.grading.grades.update', $grade) : route('club.grading.grades.store', $system)"
    :method="$grade ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.grading.field.grade')" icon="military_tech" tone="primary" cols="3" :description="__('club.grading.hint.grade')">
        <x-input-field name="name" :label="__('club.grading.field.name')" required maxlength="80" span="2" :value="old('name', $grade?->name)" />
        <x-input-field name="rank" type="number" min="0" max="999" :label="__('club.grading.field.rank')" :value="old('rank', $grade?->rank)" :hint="__('club.grading.hint.rank')" />
        <x-input-field name="color" type="color" :label="__('club.grading.field.color')" :value="old('color', $grade?->color ?? '#ffffff')" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $grade?->is_active ?? true)" span="2" />
    </x-form-group>
</x-modal>
