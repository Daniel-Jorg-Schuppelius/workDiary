{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Investitionsprogramm anlegen/bearbeiten (MVP-927). Erwartet: $program (?InvestmentProgram), $users --}}
@php($isEdit = $program !== null)
<x-modal
    :title="$isEdit ? __('investment.program.edit') : __('investment.program.create')"
    :eyebrow="__('investment.program.title')"
    icon="account_tree"
    tone="primary"
    :action="$isEdit ? route('investments.programs.update', $program) : route('investments.programs.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('investment.program.save')"
>
    <x-form-group :legend="__('investment.program.title')" icon="account_tree" tone="primary" cols="2">
        <x-input-field name="name" :label="__('investment.program.field.name')" :value="old('name', $program?->name)" required span="2" />
        <x-input-field name="starts_year" type="number" min="2000" max="2100" :label="__('investment.program.field.starts_year')" :value="old('starts_year', $program?->starts_year ?? now()->year)" required />
        <x-input-field name="ends_year" type="number" min="2000" max="2100" :label="__('investment.program.field.ends_year')" :value="old('ends_year', $program?->ends_year ?? now()->year + 4)" required />
        <x-select-field name="currency" :label="__('investment.program.field.currency')" required>
            <x-currency-options :selected="old('currency', $program?->currency->value ?? 'EUR')" />
        </x-select-field>
        <x-select-field name="responsible_user_id" :label="__('investment.program.field.responsible')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}" @selected($program?->responsible_user_id === $user->id)>{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="description" :label="__('investment.program.field.description')" rows="3" span="2">{{ old('description', $program?->description) }}</x-textarea-field>
    </x-form-group>
</x-modal>
