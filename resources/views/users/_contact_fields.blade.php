{{--
  Created on   : Wed Jun 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _contact_fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
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
    <x-input-field name="first_name"
                   :label="__('Vorname')"
                   type="text"
                   value="{{ old('first_name', $user?->first_name) }}"
                   maxlength="128" />

    <x-input-field name="middle_names"
                   :label="__('Weitere Vornamen')"
                   type="text"
                   value="{{ old('middle_names', $user?->middle_names) }}"
                   maxlength="128" />

    <x-input-field span="2" name="last_name"
                   :label="__('Nachname')"
                   type="text"
                   value="{{ old('last_name', $user?->last_name) }}"
                   maxlength="128" />
</x-form-group>

<x-form-group :legend="__('Kommunikation')" icon="call" tone="info" cols="2">
    <x-input-field name="phone"
                   :label="__('Telefon')"
                   type="text"
                   value="{{ old('phone', $user?->phone) }}"
                   maxlength="64" />

    <x-input-field name="mobile"
                   :label="__('Mobil')"
                   type="text"
                   value="{{ old('mobile', $user?->mobile) }}"
                   maxlength="64" />

    <x-input-field name="fax" :label="__('Fax')" type="text" value="{{ old('fax', $user?->fax) }}" maxlength="64" />
</x-form-group>

<x-form-group :legend="__('Adresse')" icon="home" tone="ghost" cols="2">
    <x-input-field span="2" name="address[supplement]"
                   :label="__('Adresszusatz')"
                   type="text"
                   value="{{ old('address.supplement', $addr?->supplement) }}"
                   maxlength="255" />

    <x-input-field span="2" name="address[street]"
                   :label="__('Straße und Hausnummer')"
                   type="text"
                   value="{{ old('address.street', $addr?->street) }}"
                   maxlength="255" />

    <x-input-field name="address[zip]"
                   :label="__('PLZ')"
                   type="text"
                   value="{{ old('address.zip', $addr?->zip) }}"
                   maxlength="32" />

    <x-input-field name="address[city]"
                   :label="__('Ort')"
                   type="text"
                   value="{{ old('address.city', $addr?->city) }}"
                   maxlength="128" />

    <x-input-field name="address[country_code]"
                   :label="__('Land (ISO-2)')"
                   type="text"
                   value="{{ old('address.country_code', $addr?->country_code) }}"
                   class="uppercase"
                   maxlength="2"
                   placeholder="DE" />
</x-form-group>

<x-form-group :legend="__('Bankverbindung')" icon="account_balance" tone="ghost" cols="2">
    <x-input-field span="2" name="bank[account_holder]"
                   :label="__('Kontoinhaber')"
                   type="text"
                   value="{{ old('bank.account_holder', $bank?->account_holder) }}"
                   maxlength="200" />

    <x-input-field name="bank[iban]"
                   :label="__('IBAN')"
                   type="text"
                   value="{{ old('bank.iban', $bank?->iban) }}"
                   class="font-mono"
                   maxlength="64" />

    <x-input-field name="bank[bic]"
                   :label="__('BIC')"
                   type="text"
                   value="{{ old('bank.bic', $bank?->bic) }}"
                   class="font-mono"
                   maxlength="32" />

    <x-input-field span="2" name="bank[bank_name]"
                   :label="__('Bankname')"
                   type="text"
                   value="{{ old('bank.bank_name', $bank?->bank_name) }}"
                   maxlength="200" />
</x-form-group>
