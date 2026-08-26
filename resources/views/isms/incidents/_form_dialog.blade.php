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
    $linkedRiskIds = $isEdit ? $incident->risks->pluck('sqid')->all() : [];
    $linkedControlIds = $isEdit ? $incident->controls->pluck('sqid')->all() : [];
    $ownerSqid = \App\Support\Sqid::encode(\App\Models\User::class, $incident?->owner_user_id);
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
        <x-input-field name="title" :label="__('isms.field.title')" required minlength="3" maxlength="180"
                       span="2"
                       :value="old('title', $incident?->title)" />
        <x-select-field name="category" :label="__('isms.field.category')" required>
            @foreach (\App\Enums\Isms\SecurityIncidentCategory::cases() as $category)
                <option value="{{ $category->value }}" @selected(old('category', $incident?->category?->value ?? 'other') === $category->value)>{{ $category->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="severity" :label="__('isms.field.severity')" required>
            @foreach (\App\Enums\Isms\IncidentSeverity::cases() as $severity)
                <option value="{{ $severity->value }}" @selected(old('severity', $incident?->severity?->value ?? 'medium') === $severity->value)>{{ $severity->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('isms.field.description')" rows="3" maxlength="10000"
                          span="2"
                          :value="old('description', $incident?->description)" />
        <x-input-field name="detected_at" type="date" :label="__('isms.field.detected_at')"
                       :value="old('detected_at', $incident?->detected_at?->toDateString())" />
        <x-input-field name="occurred_at" type="date" :label="__('isms.field.occurred_at')"
                       :value="old('occurred_at', $incident?->occurred_at?->toDateString())" />
        <x-select-field name="owner_user_id" :label="__('isms.field.owner')" span="2">
            <option value="">—</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->sqid }}" @selected((string) old('owner_user_id', $ownerSqid) === $owner->sqid)>{{ $owner->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('isms.group.incident_analysis')" icon="troubleshoot" tone="warning" cols="1">
        <x-textarea-field name="impact" :label="__('isms.field.impact')" rows="2" maxlength="10000"
                          placeholder="{{ __('isms.hint.incident_impact') }}"
                          :value="old('impact', $incident?->impact)" />
        <x-textarea-field name="root_cause" :label="__('isms.field.root_cause')" rows="2" maxlength="10000"
                          placeholder="{{ __('isms.hint.incident_root_cause') }}"
                          :value="old('root_cause', $incident?->root_cause)" />
        <x-textarea-field name="lessons_learned" :label="__('isms.field.lessons_learned')" rows="2" maxlength="10000"
                          placeholder="{{ __('isms.hint.incident_lessons_learned') }}"
                          :value="old('lessons_learned', $incident?->lessons_learned)" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.incident_privacy')" icon="privacy_tip" tone="secondary" cols="2">
        <label class="form-control flex-row items-center gap-2 sm:col-span-2">
            <input type="hidden" name="personal_data_affected" value="0">
            <input type="checkbox" name="personal_data_affected" value="1"
                   class="checkbox checkbox-sm"
                   @checked(old('personal_data_affected', $incident?->personal_data_affected))>
            <span class="label-text">{{ __('isms.field.personal_data_affected') }}</span>
        </label>
        <p class="text-xs text-muted sm:col-span-2">{{ __('isms.hint.personal_data_affected') }}</p>
        <x-input-field name="privacy_incident_ref" :label="__('isms.field.privacy_incident_ref')" maxlength="64"
                       span="2"
                       :value="old('privacy_incident_ref', $incident?->privacy_incident_ref)"
                       placeholder="{{ __('isms.hint.privacy_incident_ref') }}" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.incident_links')" icon="link" tone="primary" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.risks') }}</span>
            <input type="hidden" name="risk_ids[]" value="">
            <select name="risk_ids[]" multiple size="6" class="select select-bordered w-full h-auto">
                @foreach ($risks as $risk)
                    <option value="{{ $risk->sqid }}" @selected(in_array($risk->sqid, old('risk_ids', $linkedRiskIds)))>{{ $risk->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.controls') }}</span>
            <input type="hidden" name="control_ids[]" value="">
            <select name="control_ids[]" multiple size="6" class="select select-bordered w-full h-auto">
                @foreach ($controls as $control)
                    <option value="{{ $control->sqid }}" @selected(in_array($control->sqid, old('control_ids', $linkedControlIds)))>{{ $control->title }}</option>
                @endforeach
            </select>
        </label>
        <p class="text-xs text-muted sm:col-span-2">{{ __('isms.hint.incident_links') }}</p>
    </x-form-group>
</x-modal>
