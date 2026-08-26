{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erfassungs-/Bearbeitungs-Dialog Vorsorge (ArbMedVV) — bewusst ohne
  Freitext für Befunde (Datenminimierung).
  Variablen: $checkup (MedicalCheckup|null), $users (Collection id/name)
--}}
@php
    $isEdit = $checkup !== null;
    $userSqid = \App\Support\Sqid::encodeOrNull(\App\Models\User::class, $checkup?->user_id);
@endphp

<x-modal
    :title="$isEdit ? __('safety.register.action.edit') : __('safety.register.action.create_checkup')"
    :eyebrow="__('safety.register.title.checkups')"
    icon="medical_services"
    tone="primary"
    :action="$isEdit ? route('safety.checkups.update', $checkup) : route('safety.checkups.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('safety.register.action.save') : __('safety.register.action.create_checkup')">

    <x-form-group :legend="__('safety.register.title.checkups')" :description="__('safety.register.hint.no_health_data')" icon="medical_services" tone="primary" cols="2">
        <x-select-field name="user_id" :label="__('safety.register.field.user')" required span="2">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected((string) old('user_id', $userSqid) === $user->sqid)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="kind" :label="__('safety.register.field.kind')" required>
            @foreach (\App\Enums\Safety\MedicalCheckupKind::cases() as $k)
                <option value="{{ $k->value }}" @selected(old('kind', $checkup?->kind?->value ?? 'offered') === $k->value)>{{ $k->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="occasion" :label="__('safety.register.field.occasion')" maxlength="180" :value="old('occasion', $checkup?->occasion)" />
        <x-input-field name="performed_on" type="date" :label="__('safety.register.field.performed_on')" required :value="old('performed_on', $checkup?->performed_on?->toDateString() ?? now()->toDateString())" />
        <x-input-field name="next_due_on" type="date" :label="__('safety.register.field.next_due_on')" :value="old('next_due_on', $checkup?->next_due_on?->toDateString())" />
        <x-checkbox-field name="certificate_on_file" :label="__('safety.register.field.certificate_on_file')" :checked="(bool) old('certificate_on_file', $checkup?->certificate_on_file ?? false)" span="2" />
    </x-form-group>
</x-modal>
