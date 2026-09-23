{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _requirement_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Voraussetzungen je Zielgrad (in #entry-modal geladen). Variablen: $system, $version, $grade, $requirement|null, $previousOptions, $groups, $kinds, $bases --}}
@php
    $selectedKinds = (array) old('counted_event_kinds', $requirement?->counted_event_kinds ?? []);
    $selectedGroups = (array) old('counted_group_ids', collect($requirement?->counted_group_ids ?? [])->map(fn($id) => \App\Support\Sqid::encode(\App\Models\Club\ClubGroup::class, (int) $id))->all());
@endphp
<x-modal
    :title="__('club.grading.action.edit_requirement')"
    :eyebrow="$system->name . ' · v' . $version->version_no . ' · ' . $grade->name"
    icon="rule"
    tone="primary"
    size="wide"
    :action="route('club.grading.requirements.update', [$version, $grade])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.grading.card.requirement')" icon="rule" tone="primary" cols="3" :description="__('club.grading.hint.requirement')">
        <x-select-field name="previous_grade_id" :label="__('club.grading.field.previous_grade')">
            <option value="">–</option>
            @foreach ($previousOptions as $option)
                <option value="{{ $option->sqid }}" @selected((string) old('previous_grade_id', $requirement?->previous_grade_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubGrade::class, $requirement->previous_grade_id) : '') === $option->sqid)>{{ $option->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="min_age" type="number" min="0" max="120" :label="__('club.field.min_age')" :value="old('min_age', $requirement?->min_age)" />
        <x-input-field name="wait_months" type="number" min="0" max="240" :label="__('club.grading.field.wait_months')" :value="old('wait_months', $requirement?->wait_months)" :hint="__('club.grading.hint.wait_months')" />
        <x-input-field name="min_minutes" type="number" min="0" max="100000" :label="__('club.grading.field.min_minutes')" :value="old('min_minutes', $requirement?->min_minutes)" :hint="__('club.grading.hint.min_minutes')" />
        <x-input-field name="min_sessions" type="number" min="0" max="9999" :label="__('club.grading.field.min_sessions')" :value="old('min_sessions', $requirement?->min_sessions)" />
        <x-input-field name="min_minutes_per_session" type="number" min="1" max="1440" :label="__('club.grading.field.min_minutes_per_session')" :value="old('min_minutes_per_session', $requirement?->min_minutes_per_session)" />
        <x-select-field name="counting_basis" :label="__('club.grading.field.counting_basis')" required>
            @foreach ($bases as $basis)
                <option value="{{ $basis->value }}" @selected(old('counting_basis', $requirement?->counting_basis->value ?? 'since_previous_grade') === $basis->value)>{{ $basis->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="window_months" type="number" min="1" max="240" :label="__('club.grading.field.window_months')" :value="old('window_months', $requirement?->window_months)" />
        <x-input-field name="required_proof_label" :label="__('club.grading.field.required_proof_label')" maxlength="120" :value="old('required_proof_label', $requirement?->required_proof_label)" :hint="__('club.grading.hint.proof_label')" />
        <div class="fieldset">
            <span class="fieldset-label">{{ __('club.grading.field.counted_event_kinds') }}</span>
            <div class="flex flex-wrap gap-3">
                @foreach ($kinds as $kind)
                    <label class="label cursor-pointer gap-2 text-sm">
                        <input type="checkbox" class="checkbox checkbox-sm" name="counted_event_kinds[]" value="{{ $kind->value }}" @checked(in_array($kind->value, $selectedKinds, true))>
                        {{ $kind->label() }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-muted">{{ __('club.grading.hint.kinds') }}</p>
        </div>
        <x-select-field name="counted_group_ids[]" id="counted_group_ids" :label="__('club.grading.field.counted_group_ids')" multiple span="2" :hint="__('club.grading.hint.groups')">
            @foreach ($groups as $group)
                <option value="{{ $group->sqid }}" @selected(in_array($group->sqid, $selectedGroups, true))>{{ $group->name }}</option>
            @endforeach
        </x-select-field>
        <x-checkbox-field name="requires_approval" :label="__('club.grading.field.requires_approval')" :checked="(bool) old('requires_approval', $requirement?->requires_approval ?? false)" />
        <x-checkbox-field name="allows_exception" :label="__('club.grading.field.allows_exception')" :checked="(bool) old('allows_exception', $requirement?->allows_exception ?? false)" span="2" />
    </x-form-group>

</x-modal>
