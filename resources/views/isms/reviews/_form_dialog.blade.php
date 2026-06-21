{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anlage-/Bearbeitungs-Dialog Managementbewertung (in #entry-modal
  geladen): Datum, Teilnehmer, Eingaben, Entscheidungen, Folgemaßnahmen.
  Nur Entwürfe sind editierbar — freigegebene Protokolle blockt der
  AuditService (ValidationException).
  Variablen: $review (IsmsManagementReview|null), $scopes
--}}
@php
    $isEdit = $review !== null;
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_review') : __('isms.action.create_review')"
    :eyebrow="__('isms.title.reviews')"
    icon="grading"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.reviews.update', $review) : route('isms.reviews.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_review')">

    <x-form-group :legend="__('isms.group.review')" icon="grading" tone="primary" cols="2">
        @unless ($isEdit)
            <x-select-field name="scope" :label="__('isms.field.scope')" required>
                    @foreach ($scopes as $scopeOption)
                        <option value="{{ $scopeOption->sqid }}" @selected(old('scope') === $scopeOption->sqid || (old('scope') === null && $scopeOption->is_default))>{{ $scopeOption->name }}</option>
                    @endforeach
            </x-select-field>
        @endunless
        <x-input-field name="held_on" type="date" :label="__('isms.field.held_on')" required :value="old('held_on', $review?->held_on?->toDateString())" />
        <x-textarea-field name="participants" :label="__('isms.field.participants')" required rows="2" maxlength="5000" span="2" :value="old('participants', $review?->participants)" placeholder="{{ __('isms.hint.participants') }}" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.review_content')" icon="summarize" tone="info" cols="1">
        <x-textarea-field name="inputs" :label="__('isms.field.inputs')" required rows="4" maxlength="20000" :value="old('inputs', $review?->inputs)" placeholder="{{ __('isms.hint.inputs') }}" />
        <x-textarea-field name="decisions" :label="__('isms.field.decisions')" required rows="4" maxlength="20000" :value="old('decisions', $review?->decisions)" placeholder="{{ __('isms.hint.decisions') }}" />
        <x-textarea-field name="follow_ups" :label="__('isms.field.follow_ups')" rows="3" maxlength="20000" :value="old('follow_ups', $review?->follow_ups)" placeholder="{{ __('isms.hint.follow_ups') }}" />
    </x-form-group>
</x-modal>
