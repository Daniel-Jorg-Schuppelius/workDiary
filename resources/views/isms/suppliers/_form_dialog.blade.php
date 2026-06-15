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
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.supplier_link') }}</span>
            <select name="supplier_id" class="select select-bordered w-full">
                <option value="">{{ __('isms.hint.supplier_freetext') }}</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $assessment?->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}{{ $supplier->number ? ' (' . $supplier->number . ')' : '' }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.supplier_name') }}</span>
            <input type="text" name="supplier_name" maxlength="250"
                   class="input input-bordered w-full"
                   value="{{ old('supplier_name', $assessment?->supplier_name) }}"
                   placeholder="{{ __('isms.hint.supplier_name') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.criticality') }}</span>
            <select name="criticality" class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('criticality', $assessment?->criticality?->value ?? 'medium') === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.risk_rating') }}</span>
            <select name="risk_rating" class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('risk_rating', $assessment?->risk_rating?->value ?? 'medium') === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.service_description') }}</span>
            <textarea name="service_description" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('service_description', $assessment?->service_description) }}</textarea>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.scope') }}</span>
            <select name="isms_scope_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($scopes as $scopeOption)
                    <option value="{{ $scopeOption->id }}" @selected((string) old('isms_scope_id', $assessment?->isms_scope_id) === (string) $scopeOption->id)>{{ $scopeOption->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.owner') }}</span>
            <select name="owner_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $assessment?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.supplier_security')" icon="security" tone="warning" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.security_requirements') }}</span>
            <textarea name="security_requirements" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.security_requirements') }}">{{ old('security_requirements', $assessment?->security_requirements) }}</textarea>
        </label>
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
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.dpa_ref') }}</span>
            <input type="text" name="dpa_ref" maxlength="250"
                   class="input input-bordered w-full"
                   value="{{ old('dpa_ref', $assessment?->dpa_ref) }}"
                   placeholder="{{ __('isms.hint.dpa_ref') }}">
        </label>
        <p class="text-xs text-base-content/60 sm:col-span-2">{{ __('isms.hint.dpa_loose') }}</p>
    </x-form-group>

    <x-form-group :legend="__('isms.group.supplier_review')" icon="event_repeat" tone="info" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.last_review_on') }}</span>
            <input type="date" name="last_review_on"
                   class="input input-bordered w-full"
                   value="{{ old('last_review_on', $assessment?->last_review_on?->toDateString()) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.next_review_on') }}</span>
            <input type="date" name="next_review_on"
                   class="input input-bordered w-full"
                   value="{{ old('next_review_on', $assessment?->next_review_on?->toDateString()) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.findings') }}</span>
            <textarea name="findings" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('findings', $assessment?->findings) }}</textarea>
        </label>
        <p class="text-xs text-base-content/60 sm:col-span-2">{{ __('isms.hint.next_review_on') }}</p>
    </x-form-group>
</x-modal>
