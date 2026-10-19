{{--
  Created on   : Fri May 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $customer, $isDialog --}}
@php
    $isDialog = $isDialog ?? false;
    $action = $customer ? route('customers.update', $customer) : route('customers.store');
    $dialogUrl = ($customer ? route('customers.edit', $customer) : route('customers.create')) . '?dialog=1';
@endphp

<x-modal
    :title="$customer ? __('Kunde bearbeiten') : __('Neuer Kunde')"
    :eyebrow="__('Kunde')"
    icon="badge"
    tone="primary"
    :action="$action"
    :method="$customer ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$customer ? __('Speichern') : __('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ $dialogUrl }}">
    @endif

    {{-- Kompaktes Kernformular (Feature 165, MVP-1193, E4): Pflichtkern vorn,
         Zusatzgruppen aufklappbar — dieselben Felder, derselbe Request. --}}
    @php
        $allTags = $allTags ?? collect();
        $selectedTagIds = old('tag_ids', $customer?->tags?->map(fn ($t) => $t->sqid)->all() ?? []);
        $ts = (array) old('travel_settings', $customer?->travel_settings ?? []);
        $customerGroupSqid = old('customer_group_id', $customer?->classifications->firstWhere('domain', \App\Enums\Classification\ClassificationDomain::CustomerGroup->value)?->sqid);
        $billingValues = [
            old('document_locale', $customer?->document_locale),
            old('hourly_rate', $customer?->hourly_rate?->getAmount()),
            old('internal_rate', $customer?->internal_rate?->getAmount()),
            old('payment_terms_days', $customer?->payment_terms_days),
            old('skonto_percent', $customer?->skonto_percent?->getNumericValue()),
            old('buyer_reference', $customer?->buyer_reference),
            old('peppol_participant_id', $customer?->peppol_participant_id),
            old('delivery_format', $customer?->delivery_format?->value),
            old('billing_mode', $customer?->billing_mode?->value),
            old('debtor_no', $customer?->debtor_no),
            old('invoice_text', $customer?->invoice_text),
            $customer !== null && ! (bool) old('billable', $customer->billable ?? true) ? 'nicht abrechenbar' : null,
        ];
        $miscValues = [
            old('matchcode', $customer?->matchcode),
            old('vat_id', $customer?->vat_id),
            $customerGroupSqid,
            old('timezone', $customer?->timezone),
            old('homepage', $customer?->homepage),
            old('address', $customer?->address),
            (bool) old('exclude_from_reports', $customer?->exclude_from_reports ?? false),
            (bool) old('no_bulk_mail', $customer?->no_bulk_mail ?? false),
            old('comment', $customer?->comment),
            $selectedTagIds,
        ];
    @endphp

    <x-form-group :legend="__('Stammdaten')" icon="badge" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" required maxlength="200" :value="old('name', $customer?->name)" />
        <x-input-field name="company" :label="__('Firma')" maxlength="200" :value="old('company', $customer?->company)" />
        <x-input-field name="number" :label="__('Kundennummer')" maxlength="64" :value="old('number', $customer?->number)"
                       :disabled="($numberLockedBy ?? null) !== null"
                       :hint="($numberLockedBy ?? null) !== null ? __('numbering.field.locked_hint', ['program' => $numberLockedBy]) : null" />
    </x-form-group>

    <x-contact-name-fields :model="$customer" />

    <x-form-group :legend="__('Kontakt')" icon="call" tone="info" cols="2">
        <x-input-field name="email" type="email" :label="__('E-Mail')" maxlength="255" :value="old('email', $customer?->email)" />
        <x-input-field name="phone" :label="__('Telefon')" maxlength="64" :value="old('phone', $customer?->phone)" />
        <x-input-field name="mobile" :label="__('Mobil')" maxlength="64" :value="old('mobile', $customer?->mobile)" />
    </x-form-group>

    <x-form-group :legend="__('Adresse')" icon="home" tone="ghost" cols="2">
        <x-input-field name="address_street" span="2" :label="__('Straße / Hausnr.')" maxlength="255" :value="old('address_street', $customer?->address_street)" />
        <x-input-field name="address_zip" :label="__('PLZ')" maxlength="32" :value="old('address_zip', $customer?->address_zip)" />
        <x-input-field name="address_city" :label="__('Ort')" maxlength="128" :value="old('address_city', $customer?->address_city)" />
        <x-input-field name="country" :label="__('Land (ISO 2)')" maxlength="2" class="uppercase" :value="old('country', $customer?->country)" />
    </x-form-group>

    <x-form-group-collapsible :legend="__('Abrechnung & Darstellung')" icon="payments" tone="warning" cols="2"
                              :fields="['currency', 'document_locale', 'hourly_rate', 'internal_rate', 'billable', 'buyer_reference', 'peppol_participant_id', 'payment_terms_days', 'skonto_percent', 'skonto_days', 'delivery_format', 'billing_mode', 'debtor_no', 'invoice_text']"
                              :values="$billingValues">
        <x-select-field name="currency" :label="__('Währung')" required>
            <x-currency-options :selected="old('currency', $customer?->currency?->value ?? 'EUR')" />
        </x-select-field>
        {{-- Belegsprache (Feature 034, MVP-721): Rechnung/Angebot/AB/Mahnung/Lieferschein
             und Belegversand rendern in dieser Sprache; leer = Sprache der Organisation. --}}
        <x-select-field name="document_locale" :label="__('Belegsprache')" :hint="__('Sprache von Rechnung, Angebot, Mahnung, Lieferschein und Belegversand für diesen Kunden.')">
            <option value="">{{ __('Wie Organisation') }}</option>
            @foreach (\App\Support\Locales::enabled() as $code => $meta)
                <option value="{{ $code }}" @selected(old('document_locale', $customer?->document_locale) === $code)>{{ $meta['native'] }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="hourly_rate" type="number" step="0.01" min="0" :label="__('Stundensatz')" :value="old('hourly_rate', $customer?->hourly_rate?->getAmount())" />
        <x-input-field name="internal_rate" type="number" step="0.01" min="0" :label="__('Interner Satz')" :value="old('internal_rate', $customer?->internal_rate?->getAmount())" />

        <x-checkbox-field name="billable" :label="__('Abrechenbar')" :checked="old('billable', $customer?->billable ?? true)" />

        {{-- Zahlungsbedingungen (MVP-996): Vorgabe für neue Rechnungen; leer = Organisation. --}}
        <x-input-field name="payment_terms_days" type="number" min="0" max="365" :label="__('Zahlungsziel (Tage)')"
                       :value="old('payment_terms_days', $customer?->payment_terms_days)" :hint="__('Leer: Zahlungsziel der Organisation.')" />
        <div class="grid grid-cols-2 gap-3">
            <x-input-field name="skonto_percent" type="number" step="0.01" min="0.01" max="100" :label="__('Skonto %')"
                           :value="old('skonto_percent', $customer?->skonto_percent?->getNumericValue())" />
            <x-input-field name="skonto_days" type="number" min="1" max="365" :label="__('Skonto-Frist (Tage)')"
                           :value="old('skonto_days', $customer?->skonto_days)" />
        </div>
        <x-select-field name="delivery_format" :label="__('invoice-import.customer_default_format')" :hint="__('invoice-import.customer_default_format_hint')">
            <option value="">{{ __('invoice-import.no_default_format') }}</option>
            @foreach (\App\Enums\Invoicing\InvoiceDeliveryFormat::cases() as $format)
                <option value="{{ $format->value }}" @selected(old('delivery_format', $customer?->delivery_format?->value) === $format->value)>{{ $format->label() }}</option>
            @endforeach
        </x-select-field>
        {{-- E-Rechnung (Feature 045): Leitweg-ID/Käuferreferenz (BT-10) — Pflicht in der XRechnung. --}}
        <x-input-field name="buyer_reference" :label="__('invoicing.buyer_reference')" maxlength="64"
                       :value="old('buyer_reference', $customer?->buyer_reference)" :hint="__('invoicing.buyer_reference_hint')" />
        {{-- Peppol (Feature 066, MVP-734): Empfängerkennung für den Versand über
             den Access-Point-Provider. Das Schema bleibt der Peppol-Standard
             (iso6523-actorid-upis) und ist deshalb kein Formularfeld. --}}
        <x-input-field name="peppol_participant_id" :label="__('peppol.field.participant_id')" maxlength="64"
                       :value="old('peppol_participant_id', $customer?->peppol_participant_id)" :hint="__('peppol.field.participant_id_hint')" />

        @can(\App\Enums\User\Permission::FinanceConfig->value)
            <x-select-field name="billing_mode" :label="__('finance.field.billing_mode')" :hint="__('finance.field.billing_mode_hint')">
                <option value="">{{ __('finance.field.billing_mode_inherit') }}</option>
                @foreach (\App\Enums\Finance\BillingMode::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('billing_mode', $customer?->billing_mode?->value) === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>

            {{-- DATEV-Buchungsstapel (Feature 045, Priorität 2): Debitorennummer.
                 Leer ⇒ deterministische Vergaberegel (Nummernkreis-Basis + Kunden-ID). --}}
            <x-input-field name="debtor_no" :label="__('finance.datev.field.debtor_no')" maxlength="12"
                           :value="old('debtor_no', $customer?->debtor_no)" :hint="__('finance.datev.field.debtor_no_hint')" />
        @endcan
        <x-textarea-field name="invoice_text" span="2" :label="__('Rechnungstext')" rows="2" maxlength="5000" :value="old('invoice_text', $customer?->invoice_text)" />
    </x-form-group-collapsible>

    <x-form-group-collapsible :legend="__('Bankverbindung')" icon="account_balance" tone="ghost" cols="2"
                              :fields="['bank_account_holder', 'bank_iban', 'bank_bic', 'bank_name']"
                              :values="[old('bank_account_holder', $customer?->bank_account_holder), old('bank_iban', $customer?->bank_iban), old('bank_bic', $customer?->bank_bic), old('bank_name', $customer?->bank_name)]">
        <x-input-field name="bank_account_holder" span="2" :label="__('Kontoinhaber')" maxlength="200" :value="old('bank_account_holder', $customer?->bank_account_holder)" />
        <x-input-field name="bank_iban" :label="__('IBAN')" maxlength="64" class="uppercase" data-iban-check placeholder="DE00 0000 0000 0000 0000 00" :value="old('bank_iban', $customer?->bank_iban)" />
        <x-input-field name="bank_bic" :label="__('BIC')" maxlength="32" class="uppercase" :value="old('bank_bic', $customer?->bank_bic)" />
        <x-input-field name="bank_name" span="2" :label="__('Bank')" maxlength="200" :value="old('bank_name', $customer?->bank_name)" />
    </x-form-group-collapsible>

    <x-form-group-collapsible :legend="__('Anfahrt (Übersteuerung)')" icon="local_shipping" tone="ghost" cols="2"
                              :description="__('Leer = globale Einstellung erben.')"
                              :fields="['travel_settings.mode', 'travel_settings.km_source', 'travel_settings.flat_amount', 'travel_settings.rate_per_km']"
                              :values="$ts">
        <x-select-field name="travel_settings[mode]" :label="__('Modus')">
            <option value="">{{ __('— erben —') }}</option>
            <option value="flat" @selected(($ts['mode'] ?? '') === 'flat')>{{ __('Pauschale') }}</option>
            <option value="km" @selected(($ts['mode'] ?? '') === 'km')>{{ __('Kilometer') }}</option>
        </x-select-field>
        <x-select-field name="travel_settings[km_source]" :label="__('Kilometer-Quelle')">
            <option value="">{{ __('— erben —') }}</option>
            <option value="company" @selected(($ts['km_source'] ?? '') === 'company')>{{ __('Firmenstandort') }}</option>
            <option value="tour" @selected(($ts['km_source'] ?? '') === 'tour')>{{ __('Je nach Tour') }}</option>
        </x-select-field>
        <x-input-field name="travel_settings[flat_amount]" type="number" step="0.01" min="0" :label="__('Pauschale (netto €)')" :value="$ts['flat_amount'] ?? ''" />
        <x-input-field name="travel_settings[rate_per_km]" type="number" step="0.01" min="0" :label="__('Satz (€/km)')" :value="$ts['rate_per_km'] ?? ''" />
    </x-form-group-collapsible>

    <x-form-group-collapsible :legend="__('Weitere Ansprechpartner')" icon="group" tone="ghost"
                              :fields="['contact_persons']"
                              :values="\App\Services\Stammdaten\ContactPersons::normalizeAll(old('contact_persons', $customer?->contact_persons ?? []))">
        <x-contact-persons-editor :persons="$customer?->contact_persons ?? []" />
    </x-form-group-collapsible>

    <x-form-group-collapsible :legend="__('Portal & Sonstiges')" icon="tune" tone="ghost" cols="2"
                              :fields="['matchcode', 'vat_id', 'customer_group_id', 'timezone', 'homepage', 'address', 'exclude_from_reports', 'no_bulk_mail', 'comment', 'tag_ids']"
                              :values="$miscValues">
        <x-input-field name="matchcode" :label="__('Kürzel (Matchcode)')" maxlength="16" :value="old('matchcode', $customer?->matchcode)"
                       :hint="__('Für den Alias-Abgleich der Fernwartungs-Inbox, z. B. GSL.')" />
        <x-input-field name="vat_id" :label="__('USt-IdNr.')" maxlength="64" :value="old('vat_id', $customer?->vat_id)" />
        @if (($customerGroups ?? collect())->isNotEmpty())
            {{-- Kundengruppe (MVP-949) --}}
            <x-select-field name="customer_group_id" :label="__('Kundengruppe')">
                <option value="">—</option>
                @foreach ($customerGroups as $group)
                    <option value="{{ $group->sqid }}" @selected($customerGroupSqid === $group->sqid)>{{ $group->displayLabel() }}</option>
                @endforeach
            </x-select-field>
        @endif
        <x-input-field name="timezone" :label="__('Zeitzone')" maxlength="64" placeholder="Europe/Berlin" :value="old('timezone', $customer?->timezone)" />
        <x-input-field name="homepage" type="url" :label="__('Homepage')" maxlength="255" :value="old('homepage', $customer?->homepage)" />
        <x-textarea-field name="address" span="2" :label="__('Adresse (Freitext, optional)')" rows="2" maxlength="1000"
                          :value="old('address', $customer?->address)"
                          :hint="__('Wird nur genutzt, wenn die strukturierten Felder oben leer sind.')" />

        {{-- Feature 002: z. B. Arbeitgeber-Kunde mit separater Abrechnung — wirkt nur in den Auswertungen. --}}
        <x-checkbox-field name="exclude_from_reports" :label="__('In Auswertungen ausblenden')"
                          :hint="__('Zeiten bleiben erfasst; der Kunde erscheint nur nicht in den kundenbezogenen Auswertungen.')"
                          :checked="old('exclude_from_reports', $customer?->exclude_from_reports ?? false)" />

        {{-- Feature 119: Werbe-Opt-out. Getrennt von der Auswertungs-Ausblendung,
             weil es zwei verschiedene Fragen sind. Pflichtmitteilungen gehen trotzdem raus. --}}
        <x-checkbox-field name="no_bulk_mail" :label="__('Keine Sammelmails')"
                          :hint="__('Der Kunde erhält keine Rundschreiben — Pflichtmitteilungen ausgenommen.')"
                          :checked="old('no_bulk_mail', $customer?->no_bulk_mail ?? false)" />

        <x-textarea-field name="comment" span="2" :label="__('Notiz (intern)')" rows="2" maxlength="5000" :value="old('comment', $customer?->comment)" />

        <div class="md:col-span-2">
            <label class="label"><span class="label-text">{{ __('Tags') }}</span></label>
            <x-tag-picker :tags="$allTags" :selected="$selectedTagIds" />
        </div>
    </x-form-group-collapsible>

    <x-custom-fields-group :model="\App\Models\Customer\Customer::class" :subject="$customer" />
</x-modal>

{{-- Zeileneditor-JS zentral in resources/js/contact-persons.js (Vollaudit 2026-07, N43). --}}
