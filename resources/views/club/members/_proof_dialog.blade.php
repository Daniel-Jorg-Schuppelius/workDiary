{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _proof_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nachweis anlegen/bearbeiten (in #entry-modal geladen). Variablen: $member, $proof|null, $kinds, $today --}}
<x-modal
    :title="$proof ? __('club.action.edit') : __('club.grading.action.add_proof')"
    :eyebrow="$member->fullName()"
    icon="task"
    tone="primary"
    :action="$proof ? route('club.members.proofs.update', [$member, $proof]) : route('club.members.proofs.store', $member)"
    :method="$proof ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.grading.card.proofs')" icon="task" tone="primary" cols="3" :description="__('club.grading.hint.proof')">
        <x-select-field name="kind" :label="__('club.grading.field.kind')" required>
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind', $proof?->kind->value ?? 'course') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="label" :label="__('club.grading.field.label')" required maxlength="120" span="2" :value="old('label', $proof?->label)" />
        <x-input-field name="discipline" :label="__('club.field.discipline')" maxlength="60" :value="old('discipline', $proof?->discipline)" />
        <x-input-field name="minutes" type="number" min="0" max="100000" :label="__('club.attendance.field.minutes')" :value="old('minutes', $proof?->minutes)" :hint="__('club.grading.hint.external_minutes')" />
        <x-input-field name="sessions" type="number" min="0" max="9999" :label="__('club.grading.field.sessions')" :value="old('sessions', $proof?->sessions)" />
        <x-input-field name="obtained_on" type="date" :label="__('club.grading.field.obtained_on')" required :value="old('obtained_on', $proof?->obtained_on?->toDateString() ?? $today->toDateString())" />
        <x-input-field name="valid_until" type="date" :label="__('club.field.valid_to')" :value="old('valid_until', $proof?->valid_until?->toDateString())" />
        <x-input-field name="origin" :label="__('club.grading.field.origin')" maxlength="160" :value="old('origin', $proof?->origin)" />
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" span="3" :value="old('note', $proof?->note)" />
    </x-form-group>
</x-modal>
