{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: neuer allgemeiner Vertrag (Welle D, Contract-Lifecycle-Management).
     Aus der Kundenakte (Feature 157) mit vorbelegtem Kunden und, bei
     $agreementOnly, beschränkt auf AVV/NDA. --}}
@php
    $presetCustomer ??= null;
    $agreementOnly ??= false;
    $kindOptions = $agreementOnly ? \App\Enums\Contract\ContractKind::signingKinds() : \App\Enums\Contract\ContractKind::cases();
    $defaultPartnerType = $presetCustomer ? \App\Enums\Contract\ContractPartnerType::Customer->value : 'other';
@endphp
<x-modal
    :title="$agreementOnly ? __('contract-signing.dialog.new_agreement') : __('Neuer Vertrag')"
    :eyebrow="$presetCustomer?->name ?? __('Vertragsverwaltung')"
    icon="contract"
    tone="primary"
    :action="route('contracts.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Vertrag anlegen')"
>
    @if ($agreementOnly)
        <p class="mb-3 text-sm text-muted">{{ __('contract-signing.dialog.new_agreement_intro') }}</p>
    @endif
    <x-form-group :legend="__('Vertrag')" icon="contract" tone="primary" cols="2">
        <x-input-field name="title" :label="__('Titel/Bezeichnung')" :value="old('title')" required span="2" />
        <x-select-field name="kind" :label="__('Vertragsart')" required>
            @foreach ($kindOptions as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
            <option value="">{{ __('-- später zuweisen --') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('responsible_user_id') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Vertragspartner')" icon="handshake" tone="primary" cols="2">
        <x-select-field name="partner_type" :label="__('Partnerbezug')" required>
            @foreach (\App\Enums\Contract\ContractPartnerType::cases() as $pt)
                <option value="{{ $pt->value }}" @selected(old('partner_type', $defaultPartnerType) === $pt->value)>{{ $pt->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="partner_name" :label="__('Partner (Freitext)')" :value="old('partner_name')" />
        <x-select-field name="customer_id" :label="$agreementOnly ? __('Kunde') : __('Kunde (optional)')" :required="$agreementOnly">
            @unless ($agreementOnly)
                <option value="">{{ __('kein Kundenbezug') }}</option>
            @endunless
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id', $presetCustomer?->sqid) === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="supplier_id" :label="__('Lieferant (optional)')">
            <option value="">{{ __('kein Lieferantenbezug') }}</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->sqid }}" @selected((string) old('supplier_id') === $s->sqid)>{{ $s->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Laufzeit & Kündigung')" icon="event_repeat" tone="primary" cols="2">
        <x-select-field name="term_kind" :label="__('Laufzeitmodell')" required>
            @foreach (\App\Enums\Contract\ContractTermKind::cases() as $tk)
                <option value="{{ $tk->value }}" @selected(old('term_kind', 'fixed') === $tk->value)>{{ $tk->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="notice_period_days" type="number" min="0" :label="__('Kündigungsfrist (Tage)')" :value="old('notice_period_days')" />
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="starts_on" to-name="ends_on" type="date" fromId="ct-starts-on" toId="ct-ends-on"
                      :from-label="__('Beginn')" :to-label="__('Ende (leer = unbefristet)')"
                      :from="old('starts_on')" :to="old('ends_on')" />
        <x-input-field name="min_term_months" type="number" min="0" :label="__('Mindestlaufzeit (Monate)')" :value="old('min_term_months')" />
        <x-input-field name="renew_period_months" type="number" min="1" :label="__('Verlängerung um (Monate)')" :value="old('renew_period_months')" />
        <x-checkbox-field name="auto_renew" :label="__('Automatische Verlängerung')" :checked="old('auto_renew')" span="2" />
    </x-form-group>

    <x-form-group :legend="__('Indexierung')" icon="trending_up" tone="primary" cols="2">
        <x-select-field name="indexation_method" :label="__('Anpassungsregel')" required>
            @foreach (\App\Enums\Contract\IndexationMethod::cases() as $im)
                <option value="{{ $im->value }}" @selected(old('indexation_method', 'none') === $im->value)>{{ $im->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="indexation_value" type="number" step="0.0001" min="0" :label="__('Wert/Prozent')" :value="old('indexation_value')" />
        <x-input-field name="indexation_review_on" type="date" :label="__('Nächster Anpassungsstichtag')" :value="old('indexation_review_on')" />
        <x-input-field name="indexation_note" :label="__('Berechnungshinweis (kein externer Index)')" :value="old('indexation_note')" />
    </x-form-group>

    <x-form-group :legend="__('Wert & Nachweis')" icon="payments" tone="primary" cols="2">
        <x-input-field name="value_amount" type="number" step="0.01" min="0" :label="__('Vertragswert')" :value="old('value_amount')" />
        <x-select-field name="currency" :label="__('Währung')">
            <x-currency-options :selected="old('currency', 'EUR')" />
        </x-select-field>
        <x-select-field name="value_period" :label="__('Wertbezug')" required>
            <option value="once" @selected(old('value_period', 'once') === 'once')>{{ __('einmalig') }}</option>
            <option value="monthly" @selected(old('value_period') === 'monthly')>{{ __('monatlich') }}</option>
            <option value="quarterly" @selected(old('value_period') === 'quarterly')>{{ __('quartalsweise') }}</option>
            <option value="yearly" @selected(old('value_period') === 'yearly')>{{ __('jährlich') }}</option>
        </x-select-field>
        <x-select-field name="document_id" :label="__('Dokument (optional)')">
            <option value="">{{ __('kein Dokumentbezug') }}</option>
            @foreach ($documents as $d)
                <option value="{{ $d->sqid }}" @selected((string) old('document_id') === $d->sqid)>{{ $d->title }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('Notizen')" rows="2" span="2">{{ old('notes') }}</x-textarea-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
