{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _assessment_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-Dialog Risikobewertung (046-D, in #entry-modal geladen):
  legt IMMER einen neuen Entwurf an (kein Überschreiben). Der Score wird
  serverseitig berechnet und in der Historie angezeigt; valid_until ist
  für Netto-Bewertungen relevant (Restrisiko-Akzeptanz, Review-Scanner).
  Variablen: $risk (IsmsRisk)
--}}

<x-modal
    :title="__('isms.action.create_assessment')"
    :eyebrow="$risk->displayNo() . ' — ' . $risk->title"
    icon="speed"
    tone="warning"
    size="md"
    :action="route('isms.risks.assessments.store', $risk)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('isms.action.create_assessment')">

    <x-form-group :legend="__('isms.group.assessment')" icon="speed" tone="warning" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.assessment_kind') }} *</span>
            <select name="kind" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\AssessmentKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected(old('kind', 'net') === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
            <span class="label-text-alt text-base-content/60">{{ __('isms.hint.assessment_kind') }}</span>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.likelihood') }} (1–5) *</span>
            <select name="likelihood" required class="select select-bordered w-full">
                @for ($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}" @selected((int) old('likelihood', $risk->likelihood) === $i)>{{ $i }} — {{ __('isms.scale.likelihood.' . $i) }}</option>
                @endfor
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.impact') }} (1–5) *</span>
            <select name="impact" required class="select select-bordered w-full">
                @for ($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}" @selected((int) old('impact', $risk->impact) === $i)>{{ $i }} — {{ __('isms.scale.impact.' . $i) }}</option>
                @endfor
            </select>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.rationale') }}</span>
            <textarea name="rationale" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.rationale') }}">{{ old('rationale') }}</textarea>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.valid_until') }}</span>
            <input type="date" name="valid_until"
                   class="input input-bordered w-full"
                   value="{{ old('valid_until') }}">
            <span class="label-text-alt text-base-content/60">{{ __('isms.hint.assessment_valid_until') }}</span>
        </label>
    </x-form-group>
</x-modal>
