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
        <x-input-field name="certified_organization" :label="__('isms.field.certified_organization')" required maxlength="180"
                       :value="old('certified_organization')" />
        <x-input-field name="certification_body" :label="__('isms.field.certification_body')" required maxlength="180"
                       :value="old('certification_body')" />
        <x-input-field name="certificate_no" :label="__('isms.field.certificate_no')" required maxlength="120"
                       :value="old('certificate_no')" />
        <x-input-field name="issued_on" type="date" :label="__('isms.field.issued_on')" required
                       :value="old('issued_on')" />
        <x-textarea-field name="scope_description" :label="__('isms.field.scope_description')" required rows="3" maxlength="10000"
                          span="2"
                          placeholder="{{ __('isms.hint.certificate_scope') }}"
                          :value="old('scope_description')" />
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
        <x-input-field name="surveillance_audit_1_on" type="date" :label="__('isms.field.surveillance_audit_1_on')"
                       :value="old('surveillance_audit_1_on')" />
        <x-input-field name="surveillance_audit_2_on" type="date" :label="__('isms.field.surveillance_audit_2_on')"
                       :value="old('surveillance_audit_2_on')" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.certificate_document')" icon="picture_as_pdf" tone="success" cols="1">
        <x-select-field name="document_id" :label="__('isms.field.document')" :hint="__('isms.hint.certificate_document')">
            <option value="">—</option>
            @foreach ($documents as $document)
                <option value="{{ $document->sqid }}" @selected(old('document_id') === $document->sqid)>{{ $document->title }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('isms.field.notes')" rows="2" maxlength="5000"
                          :value="old('notes')" />
    </x-form-group>
</x-modal>
