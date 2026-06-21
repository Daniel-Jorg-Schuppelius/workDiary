{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _statement_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Bearbeitungs-Dialog SoA-Aussage (ApplicabilityStatement, in #entry-modal
  geladen). SoA-Regel: applicable=false ⇒ justification Pflicht
  (Request-Validierung required_if + zentral im RequirementService).
  Variablen: $statement (IsmsApplicabilityStatement, mit requirement)
--}}
@php
    $requirement = $statement->requirement;
    $applicableOld = (bool) old('applicable', $statement->applicable);
@endphp

<x-modal
    :title="__('isms.action.edit_statement')"
    :eyebrow="$requirement->ref_no . ' — ' . $requirement->title"
    icon="rule_folder"
    tone="warning"
    size="lg"
    :action="route('isms.statements.update', $statement)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('isms.action.save')">

    <x-form-group :legend="__('isms.group.soa')" icon="rule_folder" tone="warning" cols="1">
        {{-- Checkbox + Hidden-0: applicable kommt immer mit (Toggle aus = 0). --}}
        <input type="hidden" name="applicable" value="0">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="checkbox" name="applicable" value="1" class="toggle toggle-success"
                   @checked($applicableOld)>
            <span class="label-text">{{ __('isms.field.applicable') }}</span>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.justification') }} <span class="text-base-content/60">({{ __('isms.hint.justification') }})</span></span>
            <textarea name="justification" rows="2" maxlength="5000"
                      class="textarea textarea-bordered w-full">{{ old('justification', $statement->justification) }}</textarea>
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-select-field name="implementation_status" :label="__('isms.field.implementation_status')" required>
                    @foreach (\App\Enums\Isms\ControlImplementationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('implementation_status', $statement->implementation_status->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
            </x-select-field>
            <x-input-field name="evidence_note" :label="__('isms.field.evidence_note')" maxlength="10000" :value="old('evidence_note', $statement->evidence_note)" placeholder="{{ __('isms.hint.evidence_note') }}" />
        </div>
    </x-form-group>
</x-modal>
