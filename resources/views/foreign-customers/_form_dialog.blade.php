{{--
  Created on   : Mon Jun 01 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $foreignCustomer, $isDialog, $customers, $presetCustomerId --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $foreignCustomer ? route('foreign-customers.update', $foreignCustomer) : route('foreign-customers.store');
    $dialogUrl = ($foreignCustomer ? route('foreign-customers.edit', $foreignCustomer) : route('foreign-customers.create')) . '?dialog=1';
    $selectedCustomerId = old('customer_id') !== null
        ? null // bei Validierungsfehler über old() unten gematcht (Sqid)
        : ($foreignCustomer?->customer_id ?? $presetCustomerId ?? null);
    $oldCustomerSqid = old('customer_id');
@endphp

<x-modal
    :title="$foreignCustomer ? __('Fremdkunde bearbeiten') : __('Neuer Fremdkunde')"
    :eyebrow="__('Fremdkunde')"
    icon="groups"
    tone="primary"
    :action="$action"
    :method="$foreignCustomer ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$foreignCustomer ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="groups" tone="primary" cols="2">
        <x-select-field name="customer_id" :label="__('Kunde (Firma)')" required>
            <option value="">{{ __('— Kunde wählen —') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}"
                    @if ($oldCustomerSqid !== null) @selected($oldCustomerSqid === $c->sqid)
                    @else @selected($selectedCustomerId === $c->id) @endif>
                    {{ $c->displayLabel() }}
                </option>
            @endforeach
        </x-select-field>
        <x-input-field name="name"
                       :label="__('Name')"
                       type="text"
                       value="{{ old('name', $foreignCustomer?->name) }}"
                       required
                       maxlength="200" />

        <x-input-field name="company"
                       :label="__('Firma')"
                       type="text"
                       value="{{ old('company', $foreignCustomer?->company) }}"
                       maxlength="200" />
        <x-input-field name="matchcode"
                       :label="__('Kürzel (Matchcode)')"
                       type="text"
                       value="{{ old('matchcode', $foreignCustomer?->matchcode) }}"
                       maxlength="16"
                       placeholder="{{ __('z. B. GSL') }}" />
        <x-input-field name="color"
                       :label="__('Farbe')"
                       type="text"
                       value="{{ old('color', $foreignCustomer?->color) }}"
                       maxlength="16"
                       placeholder="#64748b" />
    </x-form-group>

    <x-form-group :legend="__('Kontakt')" icon="call" tone="info" cols="2">
        <x-input-field name="contact_name"
                       :label="__('Ansprechpartner')"
                       type="text"
                       value="{{ old('contact_name', $foreignCustomer?->contact_name) }}"
                       maxlength="200" />
        <x-input-field name="email"
                       :label="__('E-Mail')"
                       type="email"
                       value="{{ old('email', $foreignCustomer?->email) }}"
                       maxlength="255" />

        <x-input-field name="phone"
                       :label="__('Telefon')"
                       type="text"
                       value="{{ old('phone', $foreignCustomer?->phone) }}"
                       maxlength="64" />
        <x-input-field name="mobile"
                       :label="__('Mobil')"
                       type="text"
                       value="{{ old('mobile', $foreignCustomer?->mobile) }}"
                       maxlength="64" />
    </x-form-group>

    <x-form-group :legend="__('Adresse')" icon="home" tone="ghost" cols="2">
        <x-textarea-field span="2" name="address" :label="__('Adresse (Freitext, optional)')" rows="2" maxlength="1000">{{ old('address', $foreignCustomer?->address) }}</x-textarea-field>
        <x-input-field name="country"
                       :label="__('Land (ISO 2)')"
                       type="text"
                       value="{{ old('country', $foreignCustomer?->country) }}"
                       class="uppercase"
                       maxlength="2" />
        <x-input-field name="homepage"
                       :label="__('Homepage')"
                       type="url"
                       value="{{ old('homepage', $foreignCustomer?->homepage) }}"
                       maxlength="255" />
    </x-form-group>

    <x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
        <x-textarea-field name="comment" :label="__('Notiz (intern)')" rows="2" maxlength="5000">{{ old('comment', $foreignCustomer?->comment) }}</x-textarea-field>
    </x-form-group>
</x-modal>
