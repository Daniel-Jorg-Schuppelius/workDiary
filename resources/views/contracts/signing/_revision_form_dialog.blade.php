{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _revision_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Dialog: neue Fassung / Entwurf bearbeiten (Feature 157).
  Erwartet: $contract, $revision (null = neu), $versions (PDF-Versionen am
  Vertrag), $defaultDeclaration, $defaultOrganizationSigner.
--}}
@php
    use App\Enums\Contract\{ContractKind, SignatureParty};
    $isEdit = $revision !== null;
    $contractItem = $isEdit ? $revision->manifestItems->first(fn ($i) => $i->isContract()) : null;
    $attachmentIds = $isEdit ? $revision->manifestItems->reject(fn ($i) => $i->isContract())->pluck('document_version_id')->map(fn ($id) => \App\Support\Sqid::encode(\App\Models\Document\DocumentVersion::class, (int) $id))->all() : [];
    $customerReq = $isEdit ? $revision->requests->first(fn ($r) => $r->party === SignatureParty::Customer) : null;
    $orgReq = $isEdit ? $revision->requests->first(fn ($r) => $r->party === SignatureParty::Organization) : null;
@endphp
<x-modal
    :title="$isEdit ? __('contract-signing.dialog.edit_title', ['no' => $revision->revision_no]) : __('contract-signing.dialog.new_title')"
    :eyebrow="$contract->number . ' — ' . $contract->title"
    icon="draw"
    tone="primary"
    size="wide"
    :action="$isEdit ? route('contracts.signing.update', $revision) : route('contracts.signing.store', $contract)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('contract-signing.action.save_revision') : __('contract-signing.action.create_revision')"
>
    @if ($versions->isEmpty())
        <div class="alert alert-warning mb-3">
            <span>{{ __('contract-signing.dialog.no_pdf') }}</span>
        </div>
    @endif

    <x-form-group :legend="__('contract-signing.dialog.files')" icon="picture_as_pdf" tone="primary" cols="1">
        <x-select-field name="contract_version_id" :label="__('contract-signing.field.contract_version')" required :hint="__('contract-signing.hint.contract_version')">
            <option value="">{{ __('contract-signing.dialog.choose') }}</option>
            @foreach ($versions as $v)
                <option value="{{ $v->sqid }}" @selected(old('contract_version_id', $contractItem?->documentVersion?->sqid) === $v->sqid)>{{ $v->document?->title }} — v{{ $v->version_no }} ({{ $v->original_name }})</option>
            @endforeach
        </x-select-field>
        <fieldset class="wd-fieldset">
            <span class="fieldset-label">{{ __('contract-signing.field.attachments') }}</span>
            <div class="grid gap-1 sm:grid-cols-2">
                @foreach ($versions as $v)
                    <label class="label cursor-pointer justify-start gap-2 py-1">
                        <input type="checkbox" name="attachment_version_ids[]" value="{{ $v->sqid }}" class="checkbox checkbox-sm"
                               @checked(in_array($v->sqid, old('attachment_version_ids', $attachmentIds), true))>
                        <span class="label-text text-sm">{{ $v->document?->title }} — v{{ $v->version_no }} ({{ $v->original_name }})</span>
                    </label>
                @endforeach
            </div>
            <span class="text-xs text-muted">{{ __('contract-signing.hint.attachments') }}</span>
        </fieldset>
    </x-form-group>

    <x-form-group :legend="__('contract-signing.dialog.terms')" icon="gavel" tone="primary" cols="2">
        @if ($contract->kind === ContractKind::DataProcessing)
            <x-select-field name="controller_party" :label="__('contract-signing.field.controller_party')" required :hint="__('contract-signing.hint.controller_party')">
                @foreach (SignatureParty::cases() as $party)
                    <option value="{{ $party->value }}" @selected(old('controller_party', $revision?->controller_party?->value ?? SignatureParty::Customer->value) === $party->value)>{{ __('contract-signing.controller.' . $party->value) }}</option>
                @endforeach
            </x-select-field>
        @endif
        <x-input-field name="review_on" type="date" :label="__('contract-signing.field.review_on')" :value="old('review_on', $revision?->review_on?->toDateString())" :hint="__('contract-signing.hint.review_on')" />
        <x-textarea-field name="declaration_text" :label="__('contract-signing.field.declaration_text')" rows="3" span="2" required :hint="__('contract-signing.hint.declaration_text')">{{ old('declaration_text', $revision?->declaration_text ?? $defaultDeclaration) }}</x-textarea-field>
    </x-form-group>

    <x-form-group :legend="__('contract-signing.party.customer')" icon="person" tone="primary" cols="3">
        <x-input-field name="customer_signer_name" :label="__('contract-signing.field.signer_name')" :value="old('customer_signer_name', $customerReq?->signer_name)" required />
        <x-input-field name="customer_signer_function" :label="__('contract-signing.field.signer_function')" :value="old('customer_signer_function', $customerReq?->signer_function)" />
        <x-input-field name="customer_signer_email" type="email" :label="__('contract-signing.field.signer_email')" :value="old('customer_signer_email', $customerReq?->signer_email ?? $contract->customer?->email)" />
    </x-form-group>

    <x-form-group :legend="__('contract-signing.party.organization')" icon="apartment" tone="primary" cols="3">
        <x-input-field name="organization_signer_name" :label="__('contract-signing.field.signer_name')" :value="old('organization_signer_name', $orgReq?->signer_name ?? $defaultOrganizationSigner)" required />
        <x-input-field name="organization_signer_function" :label="__('contract-signing.field.signer_function')" :value="old('organization_signer_function', $orgReq?->signer_function)" />
        <x-input-field name="organization_signer_email" type="email" :label="__('contract-signing.field.signer_email')" :value="old('organization_signer_email', $orgReq?->signer_email)" />
        <x-checkbox-field name="organization_required" :label="__('contract-signing.field.organization_required')" :checked="old('organization_required', $orgReq === null ? true : $orgReq->required)" span="3" :hint="__('contract-signing.hint.organization_required')" />
        <x-input-field name="waiver_reason" :label="__('contract-signing.field.waiver_reason')" :value="old('waiver_reason', $orgReq?->waiver_reason)" span="3" :hint="__('contract-signing.hint.waiver_reason')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
