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
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.title') }} *</span>
            <input type="text" name="title" required minlength="3" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('title', $risk?->title) }}">
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.category') }} *</span>
            <select name="category" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\RiskCategory::cases() as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $risk?->category?->value ?? 'organizational') === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.asset_ref') }}</span>
            <input type="text" name="asset_ref" maxlength="180"
                   class="input input-bordered w-full"
                   value="{{ old('asset_ref', $risk?->asset_ref) }}"
                   placeholder="{{ __('isms.hint.asset_ref') }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.threat') }}</span>
            <textarea name="threat" rows="2" maxlength="10000"
                      class="textarea textarea-bordered w-full"
                      placeholder="{{ __('isms.hint.threat') }}">{{ old('threat', $risk?->threat) }}</textarea>
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.description') }}</span>
            <textarea name="description" rows="3" maxlength="10000"
                      class="textarea textarea-bordered w-full">{{ old('description', $risk?->description) }}</textarea>
        </label>
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
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.treatment') }} *</span>
            <select name="treatment" required class="select select-bordered w-full">
                @foreach (\App\Enums\Isms\RiskTreatment::cases() as $treatment)
                    <option value="{{ $treatment->value }}" @selected(old('treatment', $risk?->treatment?->value ?? 'mitigate') === $treatment->value)>{{ $treatment->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="form-control">
            <span class="label-text">{{ __('isms.field.review_due_on') }}</span>
            <input type="date" name="review_due_on"
                   class="input input-bordered w-full"
                   value="{{ old('review_due_on', $risk?->review_due_on?->toDateString()) }}">
        </label>
        <label class="form-control sm:col-span-2">
            <span class="label-text">{{ __('isms.field.owner') }}</span>
            <select name="owner_user_id" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) old('owner_user_id', $risk?->owner_user_id) === (string) $owner->id)>{{ $owner->name }}</option>
                @endforeach
            </select>
        </label>
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
