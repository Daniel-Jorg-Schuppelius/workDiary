{{-- Erwartet: $supplier, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $supplier ? route('suppliers.update', $supplier) : route('suppliers.store');
    $dialogUrl = ($supplier ? route('suppliers.edit', $supplier) : route('suppliers.create')) . '?dialog=1';
@endphp

<x-modal
    :title="$supplier ? __('Lieferant bearbeiten') : __('Neuer Lieferant')"
    :eyebrow="__('Lieferant')"
    icon="local_shipping"
    tone="primary"
    :action="$action"
    :method="$supplier ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$supplier ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="local_shipping" tone="primary" cols="2">
            <x-input-field name="name" :label="__('Name')" required maxlength="200" :value="old('name', $supplier?->name)" />
            <x-input-field name="number" :label="__('Lieferantennummer (intern)')" maxlength="64" :value="old('number', $supplier?->number)" />
            <x-input-field name="company" :label="__('Firma')" maxlength="200" :value="old('company', $supplier?->company)" />
            <x-input-field name="vat_id" :label="__('USt-IdNr.')" maxlength="64" :value="old('vat_id', $supplier?->vat_id)" />
            <x-input-field name="vendor_number" :label="__('Lieferantennr. (Lexoffice)')" maxlength="64" :value="old('vendor_number', $supplier?->vendor_number)" />
            <x-checkbox-field name="active" :label="__('Aktiv')" :checked="old('active', $supplier?->active ?? true)" />
        </x-form-group>

        <x-form-group :legend="__('Kontakt')" icon="call" tone="info" cols="2">
            <x-input-field name="contact_name" :label="__('Ansprechpartner')" maxlength="200" :value="old('contact_name', $supplier?->contact_name)" />
            <x-input-field name="email" type="email" :label="__('E-Mail')" maxlength="255" :value="old('email', $supplier?->email)" />
            <x-input-field name="phone" :label="__('Telefon')" maxlength="64" :value="old('phone', $supplier?->phone)" />
            <x-input-field name="mobile" :label="__('Mobil')" maxlength="64" :value="old('mobile', $supplier?->mobile)" />
        </x-form-group>

        <x-form-group :legend="__('Adresse')" icon="home" tone="ghost" cols="2">
            <x-textarea-field name="address" span="2" :label="__('Adresse (Freitext, optional)')" rows="2" maxlength="1000"
                              :value="old('address', $supplier?->address)"
                              :hint="__('Wird nur genutzt, wenn die strukturierten Felder darunter leer sind.')" />
            <x-input-field name="address_street" span="2" :label="__('Straße / Hausnr.')" maxlength="255" :value="old('address_street', $supplier?->address_street)" />
            <x-input-field name="address_zip" :label="__('PLZ')" maxlength="32" :value="old('address_zip', $supplier?->address_zip)" />
            <x-input-field name="address_city" :label="__('Ort')" maxlength="128" :value="old('address_city', $supplier?->address_city)" />
            <x-input-field name="country" :label="__('Land (ISO 2)')" maxlength="2" class="uppercase" :value="old('country', $supplier?->country)" />
            <x-input-field name="homepage" type="url" :label="__('Homepage')" maxlength="255" :value="old('homepage', $supplier?->homepage)" />
        </x-form-group>

        <x-form-group :legend="__('Darstellung')" icon="palette" tone="warning" cols="2">
            <x-select-field name="currency" :label="__('Währung')" required>
                <x-currency-options :selected="old('currency', $supplier?->currency?->value ?? 'EUR')" />
            </x-select-field>
            <x-input-field name="timezone" :label="__('Zeitzone')" maxlength="64" placeholder="Europe/Berlin" :value="old('timezone', $supplier?->timezone)" />
            <x-input-field name="color" :label="__('Farbe')" maxlength="16" placeholder="#3b82f6" :value="old('color', $supplier?->color)" />
        </x-form-group>

        <x-form-group :legend="__('Bankverbindung')" icon="account_balance" tone="ghost" cols="2">
            <x-input-field name="bank_account_holder" span="2" :label="__('Kontoinhaber')" maxlength="200" :value="old('bank_account_holder', $supplier?->bank_account_holder)" />
            <x-input-field name="bank_iban" :label="__('IBAN')" maxlength="64" class="uppercase" placeholder="DE00 0000 0000 0000 0000 00" :value="old('bank_iban', $supplier?->bank_iban)" />
            <x-input-field name="bank_bic" :label="__('BIC')" maxlength="32" class="uppercase" :value="old('bank_bic', $supplier?->bank_bic)" />
            <x-input-field name="bank_name" span="2" :label="__('Bank')" maxlength="200" :value="old('bank_name', $supplier?->bank_name)" />
        </x-form-group>

        <x-form-group :legend="__('Notizen')" icon="description" tone="ghost">
            <x-textarea-field name="comment" :label="__('Notiz (intern)')" rows="2" maxlength="5000" :value="old('comment', $supplier?->comment)" />
        </x-form-group>

        @php
            $contactPersons = old('contact_persons', $supplier?->contact_persons ?? []);
            if (empty($contactPersons)) {
                $contactPersons = [['name' => '', 'email' => '', 'phone' => '', 'primary' => true]];
            }
        @endphp

        <div class="rounded-box border border-base-300 p-3" data-contact-persons>
            <div class="mb-2 flex items-center justify-between">
                <h3 class="font-medium text-sm">{{ __('Ansprechpartner') }}</h3>
                <x-icon-btn icon="person_add" type="button" data-contact-add show-label>{{ __('Person') }}</x-icon-btn>
            </div>
            <div class="space-y-2" data-contact-rows>
                @foreach ($contactPersons as $i => $cp)
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-center" data-contact-row>
                        <input type="text" name="contact_persons[{{ $i }}][name]" value="{{ $cp['name'] ?? '' }}"
                               placeholder="{{ __('Name') }}" maxlength="200"
                               class="input input-bordered sm:col-span-3">
                        <input type="email" name="contact_persons[{{ $i }}][email]" value="{{ $cp['email'] ?? '' }}"
                               placeholder="{{ __('E-Mail') }}" maxlength="255"
                               class="input input-bordered sm:col-span-4">
                        <input type="text" name="contact_persons[{{ $i }}][phone]" value="{{ $cp['phone'] ?? '' }}"
                               placeholder="{{ __('Telefon') }}" maxlength="64"
                               class="input input-bordered sm:col-span-3">
                        <label class="label cursor-pointer gap-1 text-xs sm:col-span-1">
                            <input type="hidden" name="contact_persons[{{ $i }}][primary]" value="0">
                            <input type="checkbox" name="contact_persons[{{ $i }}][primary]" value="1"
                                   class="checkbox checkbox-xs"
                                   @checked($cp['primary'] ?? false)>
                            <span>{{ __('Primär') }}</span>
                        </label>
                        <x-icon-btn icon="close" type="button" data-contact-remove class="sm:col-span-1" :label="__('Entfernen')" />
                    </div>
                @endforeach
            </div>
            @error('contact_persons')<p class="text-error text-sm mt-1">{{ $message }}</p>@enderror
        </div>

        @php
            $allTags = $allTags ?? collect();
            $selectedTagIds = old('tag_ids', $supplier?->tags?->map(fn ($t) => $t->sqid)->all() ?? []);
        @endphp
        <div>
            <label class="label"><span class="label-text">{{ __('Tags') }}</span></label>
            <x-tag-picker :tags="$allTags" :selected="$selectedTagIds" />
        </div>
</x-modal>

{{-- Zeileneditor-JS zentral in resources/js/contact-persons.js (Vollaudit 2026-07, N43). --}}
