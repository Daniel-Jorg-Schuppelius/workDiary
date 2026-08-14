{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Stelle anlegen/bearbeiten (Feature 068, MVP-189) --}}
@php $isEdit = $requisition->exists; @endphp
<x-modal
    :title="$isEdit ? __('Stelle bearbeiten') : __('Stelle anlegen')"
    :eyebrow="__('Personal')"
    icon="work"
    tone="primary"
    :action="$isEdit ? route('recruiting.requisitions.update', $requisition) : route('recruiting.requisitions.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-form-group :legend="__('Stelle')" icon="work" tone="primary" cols="2">
        <x-input-field name="title" :label="__('Titel')" required maxlength="200" span="2" :value="old('title', $requisition->title ?? '')" />
        <x-input-field name="department" :label="__('Abteilung')" maxlength="120" :value="old('department', $requisition->department ?? '')" />
        <x-select-field name="employment_type" :label="__('Beschäftigungsart')" required>
            @foreach (\App\Models\Applications\JobRequisition::EMPLOYMENT_TYPES as $type)
                <option value="{{ $type }}" @selected(old('employment_type', $requisition->employment_type ?? 'full_time') === $type)>{{ __("values.$type") }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="headcount" type="number" min="1" max="999" :label="__('Anzahl Stellen')" :value="old('headcount', $requisition->headcount ?? 1)" />
        <x-input-field name="target_start_on" type="date" :label="__('Zielstart')" :value="old('target_start_on', optional($requisition->target_start_on)->toDateString())" />
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')" span="2">
            <option value="">{{ __('— offen —') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected(old('responsible_user_id', $requisition->responsible_user_id !== null ? \App\Support\Sqid::encode(\App\Models\User::class, $requisition->responsible_user_id) : '') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="budget_note" :label="__('Budget-/Kapazitätsbezug')" maxlength="500" span="2" :value="old('budget_note', $requisition->budget_note ?? '')" />
        <x-textarea-field name="profile" :label="__('Stellenprofil / Anforderungen')" rows="4" span="2">{{ old('profile', $requisition->profile ?? '') }}</x-textarea-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
