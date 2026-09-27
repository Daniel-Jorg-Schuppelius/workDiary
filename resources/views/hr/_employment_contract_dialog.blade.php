{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _employment_contract_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Arbeitsvertrag zur Unterschrift senden (MVP-939). Erwartet: $name, $email, $action --}}
<x-modal
    :title="__('hr.employment.title')"
    :eyebrow="$name"
    icon="draw"
    tone="primary"
    :action="$action"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('hr.employment.send')"
>
    <p class="mb-3 text-sm opacity-70">{{ __('hr.employment.intro') }}</p>
    <x-form-group :legend="__('hr.employment.title')" icon="draw" tone="primary" cols="2">
        <x-input-field name="title" :label="__('hr.employment.field.title')" :value="old('title', __('hr.employment.default_title', ['name' => $name]))" required span="2" />
        <x-input-field name="starts_on" type="date" :label="__('hr.employment.field.starts_on')" :value="old('starts_on')" required />
        <x-input-field name="email" type="email" :label="__('hr.employment.field.email')" :value="old('email', $email)" required />
        <x-textarea-field name="declaration_text" :label="__('hr.employment.field.declaration_text')" rows="2" required span="2">{{ old('declaration_text', __('hr.employment.default_declaration')) }}</x-textarea-field>
        <div class="md:col-span-2">
            <label class="label" for="employment-file"><span class="label-text">{{ __('hr.employment.field.file') }}</span></label>
            <input id="employment-file" type="file" name="file" accept="application/pdf" required class="file-input file-input-bordered file-input-sm w-full">
            @error('file')<p class="text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>
</x-modal>
