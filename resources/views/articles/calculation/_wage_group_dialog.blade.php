{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _wage_group_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Lohngruppe (MVP-1055). Variablen: $wageGroup --}}
@php
    $isEdit = $wageGroup->exists;
@endphp
<x-modal
    :title="$isEdit ? $wageGroup->name : __('article.calculation.add_wage_group')"
    icon="badge"
    tone="primary"
    :action="$isEdit ? route('articles.wage-groups.update', $wageGroup) : route('articles.wage-groups.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
    size="sm">
    <x-form-group :legend="__('article.calculation.col.wage_group')" icon="badge" tone="primary" cols="2">
        <x-input-field name="name" :label="__('article.calculation.field.wage_group_name')" required maxlength="100" span="2" :value="old('name', $wageGroup->name ?? '')" />
        <x-input-field name="hourly_wage_amount" type="number" :label="__('article.calculation.col.hourly_wage')" required min="0" step="0.01" :value="old('hourly_wage_amount', $wageGroup->hourly_wage_amount?->getAmount() ?? '')" />
        <x-input-field name="headcount" type="number" :label="__('article.calculation.col.headcount')" required min="0" step="1" :value="old('headcount', (string) ($wageGroup->headcount ?? 1))" :hint="__('article.calculation.hint.headcount')" />
        <x-input-field name="position" type="number" :label="__('Position')" min="0" step="1" :value="old('position', (string) ($wageGroup->position ?? 0))" />
        <x-checkbox-field name="is_active" :label="__('aktiv')" :checked="(bool) old('is_active', $wageGroup->is_active ?? true)" />
    </x-form-group>
    <x-validation-errors />
</x-modal>
