{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _asset_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Firmenbogen hochladen (Feature 076, MVP-296) --}}
<x-modal
    :title="__('document_design.asset.upload')"
    icon="upload_file"
    tone="primary"
    :action="route('admin.document-design.assets.store')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('document_design.asset.upload_submit')"
>
    <div class="space-y-3">
        <p class="text-sm text-base-content/60">{{ __('document_design.asset.upload_hint') }}</p>

        <label class="form-control w-full">
            <span class="label-text">{{ __('document_design.asset.name') }} *</span>
            <input type="text" name="name" required maxlength="120" value="{{ old('name') }}"
                   class="input input-bordered input-sm w-full">
        </label>

        <label class="form-control w-full">
            <span class="label-text">{{ __('document_design.asset.page_role') }} *</span>
            <select name="page_role" required class="select select-bordered select-sm w-full">
                <option value="first">{{ __('Erste Seite') }}</option>
                <option value="following">{{ __('Folgeseiten') }}</option>
            </select>
        </label>

        <label class="form-control w-full">
            <span class="label-text">{{ __('document_design.asset.file') }} * ({{ __('PDF, JPG oder PNG — A4 Hochformat') }})</span>
            <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"
                   class="file-input file-input-bordered file-input-sm w-full">
        </label>
    </div>
</x-modal>
