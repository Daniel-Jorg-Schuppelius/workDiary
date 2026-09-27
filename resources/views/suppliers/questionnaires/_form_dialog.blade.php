{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Fragebogen anlegen/bearbeiten (MVP-937). Erwartet: $questionnaire --}}
@php
    $types = array_values(array_filter(\App\Enums\Fields\FieldType::cases(), static fn (\App\Enums\Fields\FieldType $type): bool => ! $type->storesAttachment()));
    $items = old('fields', $questionnaire?->schema->toRows());
@endphp
<x-modal
    :title="$questionnaire ? __('supplier_questionnaire.edit') : __('supplier_questionnaire.create')"
    :eyebrow="__('supplier_questionnaire.title')"
    icon="fact_check"
    tone="primary"
    size="lg"
    :action="$questionnaire ? route('supplier-questionnaires.update', $questionnaire) : route('supplier-questionnaires.store')"
    :method="$questionnaire ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('supplier_questionnaire.save')"
>
    <x-form-group :legend="__('supplier_questionnaire.title')" icon="fact_check" tone="primary" cols="2">
        <x-input-field name="name" :label="__('supplier_questionnaire.field.name')" :value="old('name', $questionnaire?->name)" required />
        <x-input-field name="validity_months" type="number" min="1" max="60" :label="__('supplier_questionnaire.field.validity_months')" :value="old('validity_months', $questionnaire?->validity_months ?? 12)" required />
        <x-textarea-field name="description" :label="__('supplier_questionnaire.field.description')" rows="2" span="2">{{ old('description', $questionnaire?->description) }}</x-textarea-field>
        <x-checkbox-field name="is_active" :label="__('supplier_questionnaire.field.is_active')" :checked="(bool) old('is_active', $questionnaire?->is_active ?? true)" />
    </x-form-group>
    <x-form-group :legend="__('supplier_questionnaire.field.questions')" icon="list" tone="primary">
        <x-field-schema-editor prefix="fields" :items="$items" :types="$types" />
    </x-form-group>
</x-modal>
