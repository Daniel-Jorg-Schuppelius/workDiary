{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anlage-Dialog Datenschutzvorfall (in #entry-modal geladen). Variablen: $types, $customers --}}
<x-modal
    :title="__('Vorfall melden')"
    :eyebrow="__('Datenschutzvorfälle')"
    icon="gpp_maybe"
    tone="primary"
    :action="route('dataprotection.incidents.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-form-group :legend="__('Vorfall')" icon="gpp_maybe" tone="primary" cols="2">
        <x-input-field name="type" :label="__('Art des Vorfalls')">
            <select id="type" name="type" class="select select-bordered w-full">
                @foreach ($types as $t)<option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>@endforeach
            </select>
        </x-input-field>
        <x-input-field name="occurred_at" type="datetime-local" :label="__('Zeitpunkt des Ereignisses')" :value="old('occurred_at')" />
        <x-input-field name="summary" :label="__('Sachverhalt')" required span="2">
            <textarea id="summary" name="summary" rows="4" class="textarea textarea-bordered w-full" required>{{ old('summary') }}</textarea>
            <p class="text-xs text-base-content/60 mt-1">{{ __('Wird verschlüsselt gespeichert.') }}</p>
        </x-input-field>
        <x-input-field name="affected" :label="__('Betroffene Daten / Personen / Systeme')" span="2">
            <textarea id="affected" name="affected" rows="3" class="textarea textarea-bordered w-full">{{ old('affected') }}</textarea>
        </x-input-field>
    </x-form-group>

    <x-form-group :legend="__('Rolle & Zuständigkeit')" icon="diversity_3" tone="ghost" cols="2">
        <x-input-field name="controller_role" :label="__('Eure Rolle bei diesem Vorfall')">
            <select id="controller_role" name="controller_role" class="select select-bordered w-full">
                <option value="controller" @selected(old('controller_role', 'controller') === 'controller')>{{ __('Eigener Vorfall (Verantwortlicher, Art. 33)') }}</option>
                <option value="processor" @selected(old('controller_role') === 'processor')>{{ __('AV-Vorfall (Auftragsverarbeiter – Kunde meldet, Art. 33 Abs. 2)') }}</option>
            </select>
        </x-input-field>
        <x-input-field name="controller_name" :label="__('Verantwortlicher / Kunde (bei AV-Vorfall)')" :value="old('controller_name')" />
        <x-input-field name="controller_customer_id" :label="__('Kunde aus Stammdaten')" span="2">
            <select id="controller_customer_id" name="controller_customer_id" class="select select-bordered w-full">
                <option value="">{{ __('Kein Kunde verknüpft') }}</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->sqid }}" @selected((string) old('controller_customer_id') === $customer->sqid)>
                        {{ $customer->displayLabel() }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-base-content/60">{{ __('Bei AV-Vorfällen wird daraus die Kundenadresse für den Bundesland-Vorschlag verwendet.') }}</p>
        </x-input-field>
        <x-input-field name="own_infrastructure_affected" :label="__('Eigene Infrastruktur mitbetroffen')" span="2">
            <label class="label cursor-pointer justify-start gap-2">
                <input type="checkbox" name="own_infrastructure_affected" value="1" class="checkbox checkbox-sm" @checked(old('own_infrastructure_affected'))>
                <span class="fieldset-label">{{ __('Ja – eigene Systeme/Daten ebenfalls betroffen (ggf. zusätzlicher eigener Meldefall)') }}</span>
            </label>
        </x-input-field>
    </x-form-group>
</x-modal>
