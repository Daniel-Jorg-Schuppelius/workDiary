{{--
    Gemeinsame Eingabefelder für strukturierte Mitarbeiterdaten:
    Namensbestandteile, Kommunikation, Adresse und Bankverbindung.

    Erwartet:
      $user (App\Models\User|null) – bestehender Datensatz oder null beim Anlegen
--}}
@php
    /** @var \App\Models\User|null $user */
    $user = $user ?? null;
    $addr = $user?->primaryAddress();
    $bank = $user?->primaryBankAccount();
@endphp

<x-form-group :legend="__('Namensbestandteile')" icon="badge" tone="ghost" cols="2"
              :description="__('Für eine korrekte Erfassung (z. B. Anschreiben, Exporte). Der Anzeigename oben bleibt maßgeblich für die Darstellung in der Anwendung.')">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Vorname') }}</label>
        <input type="text" name="first_name" maxlength="128"
               class="input input-bordered w-full @error('first_name') input-error @enderror"
               value="{{ old('first_name', $user?->first_name) }}">
        @error('first_name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Weitere Vornamen') }}</label>
        <input type="text" name="middle_names" maxlength="128"
               class="input input-bordered w-full @error('middle_names') input-error @enderror"
               value="{{ old('middle_names', $user?->middle_names) }}">
        @error('middle_names')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Nachname') }}</label>
        <input type="text" name="last_name" maxlength="128"
               class="input input-bordered w-full @error('last_name') input-error @enderror"
               value="{{ old('last_name', $user?->last_name) }}">
        @error('last_name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Kommunikation')" icon="call" tone="info" cols="2">
    <div class="fieldset">
        <label class="fieldset-label">{{ __('Telefon') }}</label>
        <input type="text" name="phone" maxlength="64"
               class="input input-bordered w-full @error('phone') input-error @enderror"
               value="{{ old('phone', $user?->phone) }}">
        @error('phone')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Mobil') }}</label>
        <input type="text" name="mobile" maxlength="64"
               class="input input-bordered w-full @error('mobile') input-error @enderror"
               value="{{ old('mobile', $user?->mobile) }}">
        @error('mobile')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Fax') }}</label>
        <input type="text" name="fax" maxlength="64"
               class="input input-bordered w-full @error('fax') input-error @enderror"
               value="{{ old('fax', $user?->fax) }}">
        @error('fax')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Adresse')" icon="home" tone="ghost" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Adresszusatz') }}</label>
        <input type="text" name="address[supplement]" maxlength="255"
               class="input input-bordered w-full @error('address.supplement') input-error @enderror"
               value="{{ old('address.supplement', $addr?->supplement) }}">
        @error('address.supplement')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Straße und Hausnummer') }}</label>
        <input type="text" name="address[street]" maxlength="255"
               class="input input-bordered w-full @error('address.street') input-error @enderror"
               value="{{ old('address.street', $addr?->street) }}">
        @error('address.street')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('PLZ') }}</label>
        <input type="text" name="address[zip]" maxlength="32"
               class="input input-bordered w-full @error('address.zip') input-error @enderror"
               value="{{ old('address.zip', $addr?->zip) }}">
        @error('address.zip')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Ort') }}</label>
        <input type="text" name="address[city]" maxlength="128"
               class="input input-bordered w-full @error('address.city') input-error @enderror"
               value="{{ old('address.city', $addr?->city) }}">
        @error('address.city')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('Land (ISO-2)') }}</label>
        <input type="text" name="address[country_code]" maxlength="2" placeholder="DE"
               class="input input-bordered w-full uppercase @error('address.country_code') input-error @enderror"
               value="{{ old('address.country_code', $addr?->country_code) }}">
        @error('address.country_code')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>

<x-form-group :legend="__('Bankverbindung')" icon="account_balance" tone="ghost" cols="2">
    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Kontoinhaber') }}</label>
        <input type="text" name="bank[account_holder]" maxlength="200"
               class="input input-bordered w-full @error('bank.account_holder') input-error @enderror"
               value="{{ old('bank.account_holder', $bank?->account_holder) }}">
        @error('bank.account_holder')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('IBAN') }}</label>
        <input type="text" name="bank[iban]" maxlength="64"
               class="input input-bordered w-full font-mono @error('bank.iban') input-error @enderror"
               value="{{ old('bank.iban', $bank?->iban) }}">
        @error('bank.iban')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset">
        <label class="fieldset-label">{{ __('BIC') }}</label>
        <input type="text" name="bank[bic]" maxlength="32"
               class="input input-bordered w-full font-mono @error('bank.bic') input-error @enderror"
               value="{{ old('bank.bic', $bank?->bic) }}">
        @error('bank.bic')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>

    <div class="fieldset md:col-span-2">
        <label class="fieldset-label">{{ __('Bankname') }}</label>
        <input type="text" name="bank[bank_name]" maxlength="200"
               class="input input-bordered w-full @error('bank.bank_name') input-error @enderror"
               value="{{ old('bank.bank_name', $bank?->bank_name) }}">
        @error('bank.bank_name')<p class="text-error text-sm">{{ $message }}</p>@enderror
    </div>
</x-form-group>
