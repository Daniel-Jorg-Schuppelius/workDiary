{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Dokument (in #entry-modal geladen).
  Variablen: $document (Document|null), $documentableKind (string|null),
             $documentableId (Sqid|null — nur beim Anlegen mit Vorbelegung)
--}}
@php
    $isEdit = $document !== null;
@endphp

<x-modal
    :title="$isEdit ? __('document.action.edit') : __('document.action.create')"
    :eyebrow="__('document.title.index')"
    icon="folder_open"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('documents.update', $document) : route('documents.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('document.action.save') : __('document.action.create')">

    @unless ($isEdit)
        @if ($documentableKind !== null && $documentableId !== null)
            <input type="hidden" name="documentable_kind" value="{{ $documentableKind }}">
            <input type="hidden" name="documentable_id" value="{{ $documentableId }}">
        @endif
    @endunless

    <x-form-group :legend="__('document.title.index')" icon="folder_open" tone="primary" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('document.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full" value="{{ old('title', $document?->title) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('document.field.type') }} *</span>
            <select name="document_type" required class="select select-bordered w-full">
                @foreach (\App\Enums\Document\DocumentType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('document_type', $document?->document_type?->value ?? 'other') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('document.field.status') }}</span>
            <select name="status" class="select select-bordered w-full">
                @foreach ([\App\Enums\Document\DocumentStatus::Active, \App\Enums\Document\DocumentStatus::Draft] as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $document?->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('document.field.description') }}</span>
            <textarea name="description" rows="3" maxlength="4000"
                      class="textarea textarea-bordered w-full">{{ old('description', $document?->description) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('document.field.validity')" icon="event_available" tone="info">
        <x-date-range class="sm:col-span-2"
                      layout="split"
                      from-name="valid_from"
                      to-name="valid_until"
                      :from="old('valid_from', $document?->valid_from?->toDateString())"
                      :to="old('valid_until', $document?->valid_until?->toDateString())"
                      :from-label="__('document.field.valid_from')"
                      :to-label="__('document.field.valid_until')"
                      size="md" />
    </x-form-group>

    @unless ($isEdit)
        <x-form-group :legend="__('document.field.file')" icon="upload_file" tone="warning" cols="2">
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('document.field.file') }} *</span>
                <input type="file" name="file" required class="file-input file-input-bordered w-full"
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.log,.zip,.docx,.xlsx">
                <span class="label-text-alt mt-1 text-base-content/60">{{ __('document.hint.upload') }}</span>
            </label>
            <label class="form-control sm:col-span-2">
                <span class="label-text">{{ __('document.field.version_note') }}</span>
                <input type="text" name="version_note" maxlength="500"
                       class="input input-bordered w-full" value="{{ old('version_note') }}">
            </label>
        </x-form-group>
    @endunless
</x-modal>
