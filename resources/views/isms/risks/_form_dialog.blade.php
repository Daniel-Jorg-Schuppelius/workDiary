{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog ISMS-Risiko (in #entry-modal geladen).
  Variablen: $risk (IsmsRisk|null), $controls (Collection id/title),
             $owners (Collection id/name)
--}}
@php
    $isEdit = $risk !== null;
    $linkedControlIds = $isEdit ? $risk->controls->pluck('id')->all() : [];
@endphp

<x-modal
    :title="$isEdit ? __('isms.action.edit_risk') : __('isms.action.create_risk')"
    :eyebrow="__('isms.title.risks')"
    icon="warning_amber"
    tone="primary"
    size="lg"
    :action="$isEdit ? route('isms.risks.update', $risk) : route('isms.risks.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('isms.action.save') : __('isms.action.create_risk')">

    <x-form-group :legend="__('isms.group.risk')" icon="warning_amber" tone="primary" cols="2">
        <x-input-field name="title" :label="__('isms.field.title')" required minlength="3" maxlength="180" span="2" :value="old('title', $risk?->title)" />
        <x-select-field name="category" :label="__('isms.field.category')" required>
                @foreach (\App\Enums\Isms\RiskCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $risk?->category?->value ?? 'organizational') === $category->value)>{{ $category->label() }}</option>
                @endforeach
        </x-select-field>
        <x-input-field name="asset_ref" :label="__('isms.field.asset_ref')" maxlength="180" :value="old('asset_ref', $risk?->asset_ref)" placeholder="{{ __('isms.hint.asset_ref') }}" />
        <x-textarea-field name="threat" :label="__('isms.field.threat')" rows="2" maxlength="10000" span="2" :value="old('threat', $risk?->threat)" placeholder="{{ __('isms.hint.threat') }}" />
        <x-textarea-field name="description" :label="__('isms.field.description')" rows="3" maxlength="10000" span="2" :value="old('description', $risk?->description)" />
    </x-form-group>

    <x-form-group :legend="__('isms.group.assessment')" icon="speed" tone="warning" cols="2">
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.likelihood') }} (1–5) *</span>
            <select name="likelihood" required class="select select-bordered w-full">
                @for ($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}" @selected((int) old('likelihood', $risk?->likelihood ?? 3) === $i)>{{ $i }} — {{ __('isms.scale.likelihood.' . $i) }}</option>
                @endfor
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.impact') }} (1–5) *</span>
            <select name="impact" required class="select select-bordered w-full">
                @for ($i = 1; $i <= 5; $i++)
                    <option value="{{ $i }}" @selected((int) old('impact', $risk?->impact ?? 3) === $i)>{{ $i }} — {{ __('isms.scale.impact.' . $i) }}</option>
                @endfor
            </select>
        </label>
        <x-select-field name="treatment" :label="__('isms.field.treatment')" required>
                @foreach (\App\Enums\Isms\RiskTreatment::cases() as $treatment)
                    <option value="{{ $treatment->value }}" @selected(old('treatment', $risk?->treatment?->value ?? 'mitigate') === $treatment->value)>{{ $treatment->label() }}</option>
                @endforeach
        </x-select-field>
        <x-input-field name="review_due_on" type="date" :label="__('isms.field.review_due_on')" :value="old('review_due_on', $risk?->review_due_on?->toDateString())" />
        <x-select-field name="owner_user_id" :label="__('isms.field.owner')" span="2">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $risk?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('isms.field.controls')" icon="verified_user" tone="success" cols="1">
        {{-- Leerer Marker: sorgt dafür, dass control_ids auch bei komplett
             abgewählter Auswahl übertragen wird (Sync auf leere Liste). --}}
        <input type="hidden" name="control_ids[]" value="">
        <label class="form-control">
            <span class="label-text">{{ __('isms.hint.controls') }}</span>
            <select name="control_ids[]" multiple size="8" class="select select-bordered w-full h-auto">
                @foreach ($controls as $control)
                    <option value="{{ $control->id }}"
                            @selected(in_array($control->id, old('control_ids', $linkedControlIds)))>
                        {{ $control->title }}
                    </option>
                @endforeach
            </select>
        </label>
        @if ($controls->isEmpty())
            <p class="text-xs text-base-content/60">{{ __('isms.hint.no_controls_yet') }}</p>
        @endif
    </x-form-group>
</x-modal>
