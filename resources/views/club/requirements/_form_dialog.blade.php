{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anwesenheitsanforderung anlegen/bearbeiten (in #entry-modal geladen). Variablen: $requirement|null, $groups, $departments. --}}
@php
    $isEdit = $requirement !== null;
    $selectedGroup = old('club_group_id', $requirement?->club_group_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubGroup::class, $requirement->club_group_id) : '');
    $selectedDepartment = old('club_department_id', $requirement?->club_department_id ? \App\Support\Sqid::encode(\App\Models\Club\ClubDepartment::class, $requirement->club_department_id) : '');
@endphp
<x-modal
    :title="$isEdit ? __('club.action.edit') : __('club.competitions.action.create_requirement')"
    :eyebrow="__('club.competitions.title.requirements')"
    icon="fact_check"
    tone="primary"
    :action="$isEdit ? route('club.requirements.update', $requirement) : route('club.requirements.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">
    <x-form-group :legend="__('club.competitions.field.requirement')" icon="fact_check" tone="primary" cols="2" :description="__('club.competitions.hint.requirement_form')">
        <x-input-field name="name" :label="__('club.field.name')" required maxlength="120" span="2" :value="old('name', $requirement?->name)" />
        <x-input-field name="required_count" type="number" min="1" max="999" required :label="__('club.competitions.field.required')" :value="old('required_count', $requirement?->required_count ?? 12)" />
        <x-input-field name="period_months" type="number" min="1" max="120" required :label="__('club.competitions.field.period_months')" :value="old('period_months', $requirement?->period_months ?? 12)" />
        <x-select-field name="club_group_id" :label="__('club.field.group')" :hint="__('club.competitions.hint.requirement_scope')">
            <option value="">–</option>
            @foreach ($groups as $group)
                <option value="{{ $group->sqid }}" @selected((string) $selectedGroup === $group->sqid)>{{ $group->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="club_department_id" :label="__('club.field.department')">
            <option value="">–</option>
            @foreach ($departments as $department)
                <option value="{{ $department->sqid }}" @selected((string) $selectedDepartment === $department->sqid)>{{ $department->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="event_kind" :label="__('club.events.field.kind')" :hint="__('club.competitions.hint.requirement_kind')">
            <option value="">{{ __('club.competitions.label.all_kinds') }}</option>
            @foreach (\App\Enums\Club\ClubEventKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('event_kind', $requirement?->event_kind?->value ?? '') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-checkbox-field name="is_active" :label="__('club.field.is_active')" :checked="(bool) old('is_active', $requirement?->is_active ?? true)" />
        <x-textarea-field name="notes" :label="__('club.field.notes')" rows="2" maxlength="2000" span="2" :value="old('notes', $requirement?->notes)" />
    </x-form-group>
    @if ($isEdit)
        <x-action-form :action="route('club.requirements.destroy', $requirement)" method="DELETE" :confirm="__('club.competitions.confirm.delete_requirement', ['name' => $requirement->name])" confirm-icon="delete" confirm-tone="error" class="mt-2">
            <x-icon-btn type="submit" icon="delete" tone="ghost" size="xs" class="text-error" show-label>{{ __('club.action.delete') }}</x-icon-btn>
        </x-action-form>
    @endif
</x-modal>
