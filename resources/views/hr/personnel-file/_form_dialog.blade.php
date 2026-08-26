{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Upload-/Bearbeitungs-Dialog Personalakte (in #entry-modal geladen).
  Variablen: $member (User), $document (Document|null)
--}}
@php
    $isEdit = $document !== null;
@endphp

<x-modal
    :title="$isEdit ? __('hr.personnel_file.action.edit') : __('hr.personnel_file.action.upload')"
    :eyebrow="__('hr.personnel_file.title') . ' · ' . $member->name"
    icon="folder_shared"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('personnel-file.update', $document) : route('org.members.personnel-file.store', $member)"
    :method="$isEdit ? 'PUT' : 'POST'"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('hr.personnel_file.action.save') : __('hr.personnel_file.action.upload')">

    <x-form-group :legend="__('hr.personnel_file.title')" icon="folder_shared" tone="primary" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('hr.personnel_file.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full" value="{{ old('title', $document?->title) }}">
        </label>
        <x-select-field name="hr_category" :label="__('hr.personnel_file.field.category')" required>
            @foreach (\App\Enums\Hr\HrDocumentCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected(old('hr_category', $document?->hr_category?->value ?? 'other') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </x-select-field>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('hr.personnel_file.field.description') }}</span>
            <textarea name="description" rows="3" maxlength="4000"
                      class="textarea textarea-bordered w-full">{{ old('description', $document?->description) }}</textarea>
        </label>
        {{-- Vertraulich ist bei Personalakten fix (erzwungen im Service, kein Schalter). --}}
        <p class="flex items-center gap-2 text-xs text-base-content/70 sm:col-span-2">
            <x-icon name="lock" class="text-muted" />
            {{ __('hr.personnel_file.confidential_fixed') }}
        </p>
    </x-form-group>

    <x-form-group :legend="__('hr.personnel_file.field.validity')" icon="event_available" tone="info">
        <x-date-range class="sm:col-span-2"
                      layout="split"
                      from-name="valid_from"
                      to-name="valid_until"
                      :from="old('valid_from', $document?->valid_from?->toDateString())"
                      :to="old('valid_until', $document?->valid_until?->toDateString())"
                      :from-label="__('hr.personnel_file.field.valid_from')"
                      :to-label="__('hr.personnel_file.field.valid_until')"
                      size="md" />
    </x-form-group>

    @unless ($isEdit)
        <x-form-group :legend="__('hr.personnel_file.field.file')" icon="upload_file" tone="warning" cols="2">
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('hr.personnel_file.field.file') }} *</span>
                <input type="file" name="file" required class="file-input file-input-bordered w-full"
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx">
                <span class="label-text-alt mt-1 text-muted">{{ __('document.hint.upload', ['mb' => \App\Services\Attachments\FileAttacher::maxMb()]) }}</span>
            </label>
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('hr.personnel_file.field.version_note') }}</span>
                <input type="text" name="version_note" maxlength="500"
                       class="input input-bordered w-full" value="{{ old('version_note') }}">
            </label>
        </x-form-group>
    @endunless
</x-modal>
