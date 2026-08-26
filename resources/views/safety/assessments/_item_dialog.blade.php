{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _item_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Positions-Dialog (Gefährdung/Maßnahme/Risiko S×W vor+nach).
  Variablen: $assessment (HazardAssessment), $item (HazardAssessmentItem|null)
--}}
@php
    $isEdit = $item !== null;
@endphp

<x-modal
    :title="$isEdit ? __('safety.register.action.edit_item') : __('safety.register.action.add_item')"
    :eyebrow="$assessment->displayNo()"
    icon="warning_amber"
    tone="warning"
    :action="$isEdit ? route('safety.assessments.items.update', [$assessment, $item]) : route('safety.assessments.items.store', $assessment)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('safety.register.action.save')">

    <x-form-group :legend="__('safety.register.field.hazard')" icon="warning_amber" tone="warning" cols="1">
        <x-input-field name="hazard" :label="__('safety.register.field.hazard')" required minlength="2" maxlength="255" :value="old('hazard', $item?->hazard)" />
        <x-textarea-field name="measure" :label="__('safety.register.field.measure')" rows="2" maxlength="10000" :value="old('measure', $item?->measure)" />
    </x-form-group>

    <x-form-group :legend="__('safety.register.field.before')" icon="speed" tone="error" cols="2">
        <x-select-field name="severity_before" :label="__('safety.register.field.severity')" required>
            @for ($i = 1; $i <= 5; $i++)
                <option value="{{ $i }}" @selected((int) old('severity_before', $item?->severity_before ?? 3) === $i)>{{ $i }}</option>
            @endfor
        </x-select-field>
        <x-select-field name="likelihood_before" :label="__('safety.register.field.likelihood')" required>
            @for ($i = 1; $i <= 5; $i++)
                <option value="{{ $i }}" @selected((int) old('likelihood_before', $item?->likelihood_before ?? 3) === $i)>{{ $i }}</option>
            @endfor
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('safety.register.field.after')" :description="__('safety.register.hint.after_optional')" icon="verified" tone="success" cols="2">
        <x-select-field name="severity_after" :label="__('safety.register.field.severity')">
            <option value="">—</option>
            @for ($i = 1; $i <= 5; $i++)
                <option value="{{ $i }}" @selected((string) old('severity_after', $item?->severity_after) === (string) $i)>{{ $i }}</option>
            @endfor
        </x-select-field>
        <x-select-field name="likelihood_after" :label="__('safety.register.field.likelihood')">
            <option value="">—</option>
            @for ($i = 1; $i <= 5; $i++)
                <option value="{{ $i }}" @selected((string) old('likelihood_after', $item?->likelihood_after) === (string) $i)>{{ $i }}</option>
            @endfor
        </x-select-field>
    </x-form-group>
</x-modal>
