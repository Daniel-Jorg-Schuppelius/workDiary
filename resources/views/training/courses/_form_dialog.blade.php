{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kurs-Dialog (Feature 145). Variablen: $course (TrainingCourse|null)
--}}
@php
    $isEdit = $course !== null;
@endphp

<x-modal
    :title="$isEdit ? __('training.action.edit') : __('training.action.create_course')"
    :eyebrow="__('training.title.courses')"
    icon="school"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('training.courses.update', $course) : route('training.courses.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('training.action.save') : __('training.action.create_course')">

    <x-form-group :legend="__('training.field.course')" icon="school" tone="primary" cols="2">
        <x-input-field name="title" :label="__('training.field.title')" required minlength="2" maxlength="180" span="2" :value="old('title', $course?->title)" />
        @unless ($isEdit)
            <x-input-field name="code" :label="__('training.field.code')" maxlength="60" :value="old('code')" />
        @endunless
        <x-select-field name="provider_kind" :label="__('training.field.provider_kind')" required>
            @foreach (\App\Enums\Training\TrainingProviderKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('provider_kind', $course?->provider_kind?->value ?? 'internal') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="provider_name" :label="__('training.field.provider_name')" maxlength="180" :value="old('provider_name', $course?->provider_name)" />
        <x-input-field name="legal_basis" :label="__('training.field.legal_basis')" maxlength="180" :value="old('legal_basis', $course?->legal_basis)" />
        <x-input-field name="duration_minutes" type="number" min="1" max="10000" :label="__('training.field.duration_minutes')" :value="old('duration_minutes', $course?->duration_minutes)" />
        <x-input-field name="validity_months" type="number" min="1" max="600" :label="__('training.field.validity_months')" :value="old('validity_months', $course?->validity_months)" />
        <x-input-field name="lead_days" type="number" min="0" max="365" :label="__('training.field.lead_days')" required :value="old('lead_days', $course?->lead_days ?? 30)" />
        <x-checkbox-field name="is_mandatory" :label="__('training.field.is_mandatory')" :checked="(bool) old('is_mandatory', $course?->is_mandatory ?? true)" />
        <x-checkbox-field name="is_active" :label="__('training.field.is_active')" :checked="(bool) old('is_active', $course?->is_active ?? true)" />
    </x-form-group>

    <x-form-group :legend="__('training.field.cost')" :description="__('training.hint.cost_informational')" icon="payments" tone="info" cols="2">
        <x-input-field name="cost_amount" type="number" step="0.01" min="0" :label="__('training.field.cost_amount')" :value="old('cost_amount', $course?->cost_amount)" />
        <x-select-field name="cost_currency" :label="__('training.field.cost_currency')">
            <option value="">—</option>
            @foreach (['EUR', 'CHF', 'USD', 'GBP'] as $currency)
                <option value="{{ $currency }}" @selected(old('cost_currency', $course?->cost_currency?->value) === $currency)>{{ $currency }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('training.field.notes')" rows="2" maxlength="10000" span="2" :value="old('notes', $course?->notes)" />
    </x-form-group>
</x-modal>
