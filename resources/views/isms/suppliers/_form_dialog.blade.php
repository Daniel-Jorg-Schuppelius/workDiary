{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog ISMS-Lieferantenbewertung (in #entry-modal).
  Supplier-Bezug ist optional (Stammdaten ODER Freitext-Name). Der AVV-Bezug
  bleibt lose (Flag + Freitext), KEIN Privacy-FK.
  Variablen: $assessment (IsmsSupplierAssessment|null), $suppliers, $scopes, $owners
--}}
@php
    $isEdit = $assessment !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_supplier') : __('isms.action.create_supplier')"
    :eyebrow="__('isms.title.suppliers')"
    icon="handshake"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.suppliers.update', $assessment) : route('isms.suppliers.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_supplier')">

    <x-form-group :legend="__('isms.group.supplier')" icon="handshake" tone="primary" cols="2">
        <x-select-field name="supplier_id" :label="__('isms.field.supplier_link')">
                <option value="">{{ __('isms.hint.supplier_freetext') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->sqid }}" @selected((string) old('supplier_id', \App\Support\Sqid::encode(\App\Models\Supplier::class, $assessment?->supplier_id)) === $supplier->sqid)>{{ $supplier->name }}{{ $supplier->number ? ' (' . $supplier->number . ')' : '' }}</option>
                @endforeach
        </x-select-field>
        <x-input-field name="supplier_name" :label="__('isms.field.supplier_name')" maxlength="250" :value="old('supplier_name', $assessment?->supplier_name)" placeholder="{{ __('isms.hint.supplier_name') }}" />
        <x-select-field name="criticality" :label="__('isms.field.criticality')">
                @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('criticality', $assessment?->criticality?->value ?? 'medium') === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
        </x-select-field>
        <x-select-field name="risk_rating" :label="__('isms.field.risk_rating')">
                @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('risk_rating', $assessment?->risk_rating?->value ?? 'medium') === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
        </x-select-field>
        <x-textarea-field name="service_description" :label="__('isms.field.service_description')" rows="2" maxlength="10000" span="2" :value="old('service_description', $assessment?->service_description)" />
        <x-select-field name="isms_scope_id" :label="__('isms.field.scope')">
                <option value="">—</option>
                @foreach ($scopes as $scopeOption)
                    <option value="{{ $scopeOption->sqid }}" @selected((string) old('isms_scope_id', \App\Support\Sqid::encode(\App\Models\Isms\IsmsScope::class, $assessment?->isms_scope_id)) === $scopeOption->sqid)>{{ $scopeOption->name }}</option>
                @endforeach
        </x-select-field>
        <x-select-field name="owner_user_id" :label="__('isms.field.owner')">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->sqid }}" @selected((string) old('owner_user_id', \App\Support\Sqid::encode(\App\Models\User::class, $assessment?->owner_user_id)) === $owner->sqid)>{{ $owner->name }}</option>
                @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('isms.group.supplier_security')" icon="security" tone="warning" cols="2">
        <x-textarea-field name="security_requirements" :label="__('isms.field.security_requirements')" rows="3" maxlength="10000" span="2" :value="old('security_requirements', $assessment?->security_requirements)" placeholder="{{ __('isms.hint.security_requirements') }}" />
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="has_nda" value="1" class="checkbox" @checked(old('has_nda', $assessment?->has_nda))>
            <span class="label-text">{{ __('isms.field.has_nda') }}</span>
        </label>
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="audit_right" value="1" class="checkbox" @checked(old('audit_right', $assessment?->audit_right))>
            <span class="label-text">{{ __('isms.field.audit_right') }}</span>
        </label>
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="has_dpa" value="1" class="checkbox" @checked(old('has_dpa', $assessment?->has_dpa))>
            <span class="label-text">{{ __('isms.field.has_dpa') }}</span>
        </label>
        <x-input-field name="dpa_ref" :label="__('isms.field.dpa_ref')" maxlength="250" :value="old('dpa_ref', $assessment?->dpa_ref)" placeholder="{{ __('isms.hint.dpa_ref') }}" />
        <x-select-field name="processing_agreement_id" :label="__('isms.field.processing_agreement')" span="2" :hint="__('isms.hint.processing_agreement')">
                <option value="">{{ __('isms.hint.processing_agreement_none') }}</option>
                @foreach ($agreements as $agreementOption)
                    <option value="{{ $agreementOption->sqid }}" @selected((string) old('processing_agreement_id', $assessment?->processingAgreement?->sqid) === (string) $agreementOption->sqid)>{{ $agreementOption->title }}{{ $agreementOption->processor?->name ? ' — ' . $agreementOption->processor->name : '' }}</option>
                @endforeach
        </x-select-field>
        <p class="text-xs text-base-content/60 sm:col-span-2">{{ __('isms.hint.dpa_loose') }}</p>
    </x-form-group>

    <x-form-group :legend="__('isms.group.supplier_review')" icon="event_repeat" tone="info" cols="2">
        <x-input-field name="last_review_on" type="date" :label="__('isms.field.last_review_on')" :value="old('last_review_on', $assessment?->last_review_on?->toDateString())" />
        <x-input-field name="next_review_on" type="date" :label="__('isms.field.next_review_on')" :value="old('next_review_on', $assessment?->next_review_on?->toDateString())" />
        <x-textarea-field name="findings" :label="__('isms.field.findings')" rows="2" maxlength="10000" span="2" :value="old('findings', $assessment?->findings)" />
        <p class="text-xs text-base-content/60 sm:col-span-2">{{ __('isms.hint.next_review_on') }}</p>
    </x-form-group>
</x-modal>
