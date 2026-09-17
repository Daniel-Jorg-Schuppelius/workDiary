{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Legal Hold setzen (MVP-801). Variablen: $users, $customers --}}
<x-modal
    :title="__('Legal Hold setzen')"
    :eyebrow="__('Datenschutz')"
    icon="gavel"
    tone="warning"
    :action="route('dataprotection.legal-holds.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Legal Hold setzen')">

    <x-form-group :legend="__('Betroffen')" :description="__('Genau eines von beiden: die Person oder der Kunde, deren Daten erhalten bleiben müssen.')" icon="gavel" tone="warning" cols="2">
        <x-select-field name="kind" :label="__('Art')" required span="2">
            <option value="user">{{ __('Person') }}</option>
            <option value="customer">{{ __('Kunde') }}</option>
        </x-select-field>
        <x-select-field name="user_id" :label="__('Person')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}">{{ $user->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="customer_id" :label="__('Kunde')">
            <option value="">—</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->sqid }}">{{ $customer->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Verfahren')" icon="description" tone="ghost">
        <x-input-field name="reference" :label="__('Aktenzeichen')" maxlength="120" />
        <x-textarea-field name="reason" :label="__('Grund')" rows="3" required
                          :hint="__('Pflicht, mindestens 10 Zeichen. Wird verschlüsselt gespeichert.')" />
    </x-form-group>
</x-modal>
