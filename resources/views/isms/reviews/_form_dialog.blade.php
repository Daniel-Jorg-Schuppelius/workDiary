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
            <label class="form-control">
                <span class="label-text">{{ __('isms.field.scope') }} *</span>
                <select name="scope" required class="select select-bordered w-full">
                    @foreach ($scopes as $scopeOption)
                        <option value="{{ $scopeOption->sqid }}" @selected(old('scope') === $scopeOption->sqid || (old('scope') === null && $scopeOption->is_default))>{{ $scopeOption->name }}</option>
                    @endforeach
                </select>
            </label>
        @endunless
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.held_on') }} *</span>
            <input type="date" name="held_on" required
                   class="input input-bordered w-full"
                   value="{{ old('held_on', $review?->held_on?->toDateString()) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.participants') }} *</span>
            <textarea name="participants" required rows="2" maxlength="5000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.participants') }}">{{ old('participants', $review?->participants) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.review_content')" icon="summarize" tone="info" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.inputs') }} *</span>
            <textarea name="inputs" required rows="4" maxlength="20000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.inputs') }}">{{ old('inputs', $review?->inputs) }}</textarea>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.decisions') }} *</span>
            <textarea name="decisions" required rows="4" maxlength="20000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.decisions') }}">{{ old('decisions', $review?->decisions) }}</textarea>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.follow_ups') }}</span>
            <textarea name="follow_ups" rows="3" maxlength="20000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.follow_ups') }}">{{ old('follow_ups', $review?->follow_ups) }}</textarea>
        </label>
    </x-form-group>
</x-modal>
