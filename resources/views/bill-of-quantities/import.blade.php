{{--
  Created on   : Sun Jun 28 2026
  License      : AGPL-3.0-or-later

  GAEB-Import-Dialog (Feature 049, MVP-081). Datei + optionales Projekt; der
  Preflight läuft serverseitig im BillOfQuantityController.
--}}
@php($isDialog = $isDialog ?? false)
<x-modal
    :title="__('gaeb.import.title')"
    :eyebrow="__('gaeb.title')"
    icon="upload"
    tone="primary"
    size="lg"
    :action="route('bill-of-quantities.import')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('gaeb.import.submit')"
>
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('bill-of-quantities.import-form') . '?dialog=1' }}">
    @endif

    <div>
        <label class="label" for="gaeb-file">
            <span class="label-text">{{ __('gaeb.import.file') }}</span>
        </label>
        <input id="gaeb-file"
               type="file"
               name="file"
               accept=".xml,.x81,.x82,.x83,.x84,.x85,.x86,.X81,.X83,.X86,.d81,.d83,.d86,text/xml,application/xml,text/plain"
               required
               class="file-input file-input-bordered w-full @error('file') file-input-error @enderror">
        <p class="text-sm text-muted mt-1">{{ __('gaeb.import.file_hint') }}</p>
        @error('file')
            <p class="text-error text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>

    <x-input-field name="name" :label="__('gaeb.import.name')" maxlength="255" :value="old('name')" />
    <p class="text-sm text-muted -mt-2">{{ __('gaeb.import.name_hint') }}</p>

    @if ($projects->isNotEmpty())
        <x-project-select name="project" :label="__('gaeb.import.project')" :placeholder="__('gaeb.import.project_none')"
            :projects="$projects" :selected="(string) old('project')" />
    @endif
</x-modal>
