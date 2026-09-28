{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _submit_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Unterlage zur eigenen Personalakte einreichen (MVP-987).
--}}
<x-modal
    :title="__('hr.personnel_file.action.submit')"
    :eyebrow="__('hr.personnel_file.title_mine')"
    icon="outbox"
    tone="primary"
    :action="route('account.personnel-file.submit')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('hr.personnel_file.action.submit')">
    <p class="text-sm text-muted">{{ __('hr.personnel_file.hint.submit') }}</p>
    <x-input-field name="title" :label="__('hr.personnel_file.field.title')" required minlength="3" maxlength="180" :value="old('title')" />
    <x-select-field name="hr_category" :label="__('hr.personnel_file.field.category')" required>
        @foreach (\App\Enums\Hr\HrDocumentCategory::cases() as $category)
            <option value="{{ $category->value }}" @selected(old('hr_category', 'certificate') === $category->value)>{{ $category->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="note" :label="__('hr.personnel_file.field.note')" maxlength="500" :value="old('note')" />
    <label class="form-control">
        <span class="label-text">{{ __('hr.personnel_file.field.file') }} *</span>
        <input type="file" name="file" required class="file-input file-input-bordered w-full"
               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx">
        <span class="label-text-alt mt-1 text-muted">{{ __('document.hint.upload', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</span>
    </label>
</x-modal>
