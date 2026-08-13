{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $isDialog = $isDialog ?? false;
@endphp

<x-modal
    :title="__('Zeitkonto anlegen')"
    :eyebrow="__('Zeitkonto')"
    icon="account_balance"
    tone="primary"
    :action="route('admin.time-accounts.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('admin.time-accounts.create') }}">
    @endif

    <x-form-group :legend="__('Konto')" icon="account_balance" tone="primary" cols="2">
        <x-input-field name="code" :label="__('Code (eindeutig, z. B. nightshift)')" :value="old('code')" required maxlength="64" />
        <x-input-field name="name" :label="__('Name')" :value="old('name')" required />
        <x-select-field name="unit" :label="__('Einheit')" required>
            @foreach (\App\Enums\TimeAccount\TimeAccountUnit::cases() as $unit)
                <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>{{ $unit->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="carryover_policy" :label="__('Übertrag')" required>
            @foreach (\App\Enums\TimeAccount\CarryoverPolicy::cases() as $policy)
                <option value="{{ $policy->value }}" @selected(old('carryover_policy', 'carry') === $policy->value)>{{ $policy->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="cap_amount" type="number" step="0.01" :label="__('Kappungsgrenze (bei Kappung)')" :value="old('cap_amount')" />
        <x-input-field name="warn_threshold" type="number" step="0.01" :label="__('Ampel: Gelb ab (absolut)')" :value="old('warn_threshold')" />
        <x-input-field name="critical_threshold" type="number" step="0.01" :label="__('Ampel: Rot ab (absolut)')" :value="old('critical_threshold')" />
        <label class="label cursor-pointer justify-start gap-3 md:col-span-2">
            <input type="hidden" name="show_on_terminal" value="0">
            <input type="checkbox" name="show_on_terminal" value="1" class="checkbox checkbox-sm"
                   @checked(old('show_on_terminal'))>
            <span class="label-text">{{ __('Kontostand in der Terminal-Statusantwort anzeigen') }}</span>
        </label>
    </x-form-group>
</x-modal>
