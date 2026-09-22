{{--
  Created on   : Tue Sep 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Abteilungs-Dialog (in #entry-modal geladen). Variablen: $department (ClubDepartment|null)
--}}
@php($isEdit = $department !== null)

<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.action.create_department')"
    :eyebrow="__('club.title.departments')"
    icon="account_tree"
    tone="primary"
    :action="$isEdit ? route('club.departments.update', $department) : route('club.departments.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('club.action.save') : __('club.action.create_department')">

    <x-form-group :legend="__('club.card.master_data')" icon="account_tree" tone="primary" cols="2">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="120" :value="old('name', $department?->name)" />
        <x-input-field name="discipline" :label="__('club.field.discipline')" maxlength="120" :value="old('discipline', $department?->discipline)" :hint="__('club.hint.discipline')" />
        <x-textarea-field name="description" :label="__('club.field.description')" rows="2" maxlength="2000" span="2" :value="old('description', $department?->description)" />
        <x-input-field name="sort_order" type="number" min="0" max="9999" :label="__('club.field.sort_order')" :value="old('sort_order', $department?->sort_order ?? 0)" />
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $department?->is_active ?? true)" />
    </x-form-group>
</x-modal>
