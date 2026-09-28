{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _accept_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einreichung in die Akte übernehmen (MVP-987). Variablen: $submission
--}}
<x-modal
    :title="__('hr.personnel_file.action.accept')"
    :eyebrow="__('hr.personnel_file.title') . ' · ' . $submission->user?->name"
    icon="check"
    tone="primary"
    :action="route('personnel-file.submissions.accept', $submission)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('hr.personnel_file.action.accept')">
    <p class="text-sm text-muted">{{ $submission->original_name }}@if ($submission->note) · {{ $submission->note }}@endif</p>
    <x-input-field name="title" :label="__('hr.personnel_file.field.title')" required minlength="3" maxlength="180" :value="old('title', $submission->title)" />
    <x-select-field name="hr_category" :label="__('hr.personnel_file.field.category')" required>
        @foreach (\App\Enums\Hr\HrDocumentCategory::cases() as $category)
            <option value="{{ $category->value }}" @selected(old('hr_category', $submission->hr_category->value) === $category->value)>{{ $category->label() }}</option>
        @endforeach
    </x-select-field>
    <x-date-range layout="split"
                  from-name="valid_from"
                  to-name="valid_until"
                  :from="old('valid_from')"
                  :to="old('valid_until')"
                  :from-label="__('hr.personnel_file.field.valid_from')"
                  :to-label="__('hr.personnel_file.field.valid_until')"
                  size="md" />
    <x-checkbox-field name="is_ack_required" :label="__('hr.personnel_file.field.is_ack_required')" :checked="(bool) old('is_ack_required', false)" />
</x-modal>
