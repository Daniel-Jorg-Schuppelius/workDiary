{{--
  Created on   : Sat Nov 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _supporting_document_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Unterlage zur Rechnung hinzufügen (Feature 196): Datei oder Link. Erwartet: $carrier, $routePrefix, $eyebrow, $mode (upload|link) --}}
@php $isLink = $mode === 'link'; @endphp
<x-modal
    :title="$isLink ? __('invoicing.supporting_documents.dialog.link_title') : __('invoicing.supporting_documents.dialog.upload_title')"
    :eyebrow="$eyebrow"
    :icon="$isLink ? 'link' : 'attach_file'"
    tone="primary"
    :action="$isLink ? route($routePrefix . '.link', $carrier) : route($routePrefix . '.upload', $carrier)"
    method="POST"
    :enctype="$isLink ? null : 'multipart/form-data'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('invoicing.supporting_documents.dialog.submit')"
>
    <x-form-group :legend="__('invoicing.supporting_documents.heading')" icon="attach_file" tone="primary">
        @if ($isLink)
            <x-input-field name="url" type="url" :label="__('invoicing.supporting_documents.dialog.url')" required maxlength="2048"
                           :value="old('url')" :hint="__('invoicing.supporting_documents.dialog.url_hint')" />
        @else
            <div>
                <label class="label" for="supporting-document-file"><span class="label-text">{{ __('invoicing.supporting_documents.dialog.file') }}</span></label>
                <input id="supporting-document-file" type="file" name="file" required
                       accept="application/pdf,image/png,image/jpeg,text/csv,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.oasis.opendocument.spreadsheet"
                       class="file-input file-input-bordered file-input-sm w-full">
                <p class="mt-1 text-xs text-base-content/70">{{ __('invoicing.supporting_documents.dialog.file_hint') }}</p>
                @error('file')<p class="text-sm text-error">{{ $message }}</p>@enderror
            </div>
        @endif
        <x-input-field name="description" :label="__('invoicing.supporting_documents.dialog.description')" maxlength="255"
                       :required="$isLink" :value="old('description')" :hint="__('invoicing.supporting_documents.dialog.description_hint')" />
    </x-form-group>
</x-modal>
