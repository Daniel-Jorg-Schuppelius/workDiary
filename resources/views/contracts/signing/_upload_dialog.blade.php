{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _upload_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dialog: extern eingegangenes, unterschriebenes PDF nachtragen (Feature 157).
  Erwartet: $contract, $signatureRequest (mit revision).
--}}
<x-modal
    :title="__('contract-signing.dialog.upload_title', ['party' => $signatureRequest->party->label()])"
    :eyebrow="$contract->number . ' — ' . $contract->title"
    icon="upload_file"
    tone="primary"
    :action="route('contracts.signing.requests.upload.store', $signatureRequest)"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('contract-signing.action.upload_evidence')"
>
    <p class="mb-3 text-sm text-muted">{{ __('contract-signing.dialog.upload_intro') }}</p>

    <x-form-group :legend="__('contract-signing.dialog.upload_legend')" icon="picture_as_pdf" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="upload-evidence-file">{{ __('contract-signing.field.evidence_file') }}</label>
            <input id="upload-evidence-file" type="file" name="evidence_file" accept="application/pdf" class="file-input file-input-bordered w-full" required>
            <span class="text-xs text-muted">{{ __('contract-signing.hint.evidence_file') }}</span>
        </div>
        <x-input-field name="signer_name" id="upload-signer-name" :label="__('contract-signing.field.signer_name')" :value="old('signer_name', $signatureRequest->signer_name)" required />
        <x-input-field name="stated_signed_on" id="upload-stated-signed-on" type="date" :label="__('contract-signing.field.stated_signed_on')" :value="old('stated_signed_on')" :max="now()->toDateString()" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
