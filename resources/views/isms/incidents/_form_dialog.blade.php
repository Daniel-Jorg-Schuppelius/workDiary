{{--
  Created on   : Sat Jun 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog ISMS-Sicherheitsvorfall (in #entry-modal).
  Variablen: $incident (IsmsSecurityIncident|null), $risks, $controls, $owners
--}}
@php
    $isEdit = $incident !== null;
    $linkedRiskIds = $isEdit ? $incident->risks->pluck('id')->all() : [];
    $linkedControlIds = $isEdit ? $incident->controls->pluck('id')->all() : [];
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_incident') : __('isms.action.create_incident')"
    :eyebrow="__('isms.title.incidents')"
    icon="report"
    tone="error"
    size="lg"
    :action="$isEdit ? route('isms.incidents.update', $incident) : route('isms.incidents.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_incident')">

    <x-form-group :legend="__('isms.group.incident')" icon="report" tone="error" cols="2">
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $incident?->title) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.category') }} *</span>
            <select name="category" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\SecurityIncidentCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $incident?->category?->value ?? 'other') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.severity') }} *</span>
            <select name="severity" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('severity', $incident?->severity?->value ?? 'medium') === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.description') }}</span>
            <textarea name="description" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('description', $incident?->description) }}</textarea>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.detected_at') }}</span>
            <input type="date" name="detected_at"
                   class="input input-bordered w-full"
                   value="{{ old('detected_at', $incident?->detected_at?->toDateString()) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.occurred_at') }}</span>
            <input type="date" name="occurred_at"
                   class="input input-bordered w-full"
                   value="{{ old('occurred_at', $incident?->occurred_at?->toDateString()) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.owner') }}</span>
            <select name="owner_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $incident?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.incident_analysis')" icon="troubleshoot" tone="warning" cols="1">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.impact') }}</span>
            <textarea name="impact" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.incident_impact') }}">{{ old('impact', $incident?->impact) }}</textarea>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.root_cause') }}</span>
            <textarea name="root_cause" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.incident_root_cause') }}">{{ old('root_cause', $incident?->root_cause) }}</textarea>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.lessons_learned') }}</span>
            <textarea name="lessons_learned" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.incident_lessons_learned') }}">{{ old('lessons_learned', $incident?->lessons_learned) }}</textarea>
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.incident_privacy')" icon="privacy_tip" tone="secondary" cols="2">
        <label class="form-control flex-row items-center gap-2 sm:col-span-2">
            <input type="hidden" name="personal_data_affected" value="0">
            <input type="checkbox" name="personal_data_affected" value="1"
                   class="checkbox checkbox-sm"
                   @checked(old('personal_data_affected', $incident?->personal_data_affected))>
            <span class="label-text">{{ __('isms.field.personal_data_affected') }}</span>
        </label>
        <p class="text-xs text-base-content/60 sm:col-span-2">{{ __('isms.hint.personal_data_affected') }}</p>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.privacy_incident_ref') }}</span>
            <input type="text" name="privacy_incident_ref" maxlength="64"
                   class="input input-bordered w-full"
                   value="{{ old('privacy_incident_ref', $incident?->privacy_incident_ref) }}"
                   placeholder="{{ __('isms.hint.privacy_incident_ref') }}">
        </label>
    </x-form-group>

    <x-form-group :legend="__('isms.group.incident_links')" icon="link" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.risks') }}</span>
            <input type="hidden" name="risk_ids[]" value="">
            <select name="risk_ids[]" multiple size="6" class="select select-bordered w-full h-auto">
                @foreach ($risks as $risk)
                    <option value="{{ $risk->id }}" @selected(in_array($risk->id, old('risk_ids', $linkedRiskIds)))>{{ $risk->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.controls') }}</span>
            <input type="hidden" name="control_ids[]" value="">
            <select name="control_ids[]" multiple size="6" class="select select-bordered w-full h-auto">
                @foreach ($controls as $control)
                    <option value="{{ $control->id }}" @selected(in_array($control->id, old('control_ids', $linkedControlIds)))>{{ $control->title }}</option>
                @endforeach
            </select>
        </label>
        <p class="text-xs text-base-content/60 sm:col-span-2">{{ __('isms.hint.incident_links') }}</p>
    </x-form-group>
</x-modal>
