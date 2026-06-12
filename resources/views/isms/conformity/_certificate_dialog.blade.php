{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _certificate_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  „Zertifikat hinterlegen"-Dialog (in #entry-modal geladen): alle
  046-Pflichtfelder (Norm + Ausgabe ergeben sich aus dem NormStatus),
  Gültigkeitszeitraum als x-date-range, Überwachungstermine optional,
  Zertifikats-PDF optional aus dem Dokumentenmodul.
  Variablen: $status (IsmsNormStatus, mit scope), $documents (id/title)
--}}

<x-modal
    :title="__('isms.action.add_certificate')"
    :eyebrow="$status->normLabel() . ' · ' . ($status->scope?->name ?? '')"
    icon="workspace_premium"
    tone="primary"
    size="lg"
    :action="route('isms.conformity.certificates.store', $status)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('isms.action.add_certificate')">

    <x-form-group :legend="__('isms.group.certificate')" icon="workspace_premium" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.certified_organization') }} *</span>
            <input type="text" name="certified_organization" required maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('certified_organization') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.certification_body') }} *</span>
            <input type="text" name="certification_body" required maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('certification_body') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.certificate_no') }} *</span>
            <input type="text" name="certificate_no" required maxlength="120"
                   class="input input-bordered w-full"
                   value="{{ old('certificate_no') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.issued_on') }} *</span>
            <input type="date" name="issued_on" required
                   class="input input-bordered w-full"
                   value="{{ old('issued_on') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.scope_description') }} *</span>
            <textarea name="scope_description" required rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.certificate_scope') }}">{{ old('scope_description') }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.validity')" icon="event" tone="info" cols="2">
        <x-date-range class="sm:col-span-2"
                      layout="split"
                      from-name="valid_from"
                      to-name="valid_until"
                      :from="old('valid_from')"
                      :to="old('valid_until')"
                      :from-label="__('isms.field.valid_from') . ' *'"
                      :to-label="__('isms.field.valid_until') . ' *'"
                      :required="true"
                      size="md" />
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.surveillance_audit_1_on') }}</span>
            <input type="date" name="surveillance_audit_1_on"
                   class="input input-bordered w-full"
                   value="{{ old('surveillance_audit_1_on') }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.surveillance_audit_2_on') }}</span>
            <input type="date" name="surveillance_audit_2_on"
                   class="input input-bordered w-full"
                   value="{{ old('surveillance_audit_2_on') }}">
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.certificate_document')" icon="picture_as_pdf" tone="success" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.document') }}</span>
            <select name="document_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($documents as $document)
                    <option value="{{ $document->sqid }}" @selected(old('document_id') === $document->sqid)>{{ $document->title }}</option>
                @endforeach
            </select>
            <span class="label-text-alt text-base-content/60">{{ __('isms.hint.certificate_document') }}</span>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.notes') }}</span>
            <textarea name="notes" rows="2" maxlength="5000"
                      class="textarea textarea-bordered w-full">{{ old('notes') }}</textarea>
        </label>
    </x-form-group>
</x-modal>
