{{-- Shared form fields for Invoice create --}}

{{-- Logik in Alpine.data("invoiceContentSwitch") (components.js) — der
     CSP-Build-Parser kennt keine if-Statements in Direktiven. --}}
<div x-data="invoiceContentSwitch"
     data-content="{{ old('content', 'service') }}"
     x-on:change="onFormChange($event)">
<x-form-group :legend="__('Filter')" icon="receipt_long" tone="primary" cols="2">
    <x-select-field name="customer_id" :label="__('Kunde')" required span="2">
        <option value="">{{ __('-- bitte wählen --') }}</option>
        @foreach ($customers as $c)
            <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="project_id" :label="__('Projekt (optional)')" span="2" data-depends-on="customer_id">
        <option value="">{{ __('alle Projekte des Kunden') }}</option>
        @foreach ($projects as $p)
            <option value="{{ $p->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $p->customer_id) }}" @selected((string) old('project_id') === $p->sqid)>{{ $p->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="foreign_customer_id" :label="__('Fremdkunde / Endkunde (optional)')" span="2" data-depends-on="customer_id" :hint="__('Nur Zeiten dieses Endkunden abrechnen.')">
        <option value="">{{ __('alle Endkunden') }}</option>
        @foreach (($foreignCustomers ?? collect()) as $fc)
            <option value="{{ $fc->sqid }}" data-parent="{{ \App\Support\Sqid::encode(\App\Models\Customer::class, $fc->customer_id) }}" @selected((string) old('foreign_customer_id') === $fc->sqid)>{{ $fc->company ?: $fc->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="content" :label="__('Inhalt')" span="2" :hint="__('Material wird getrennt als eigene Rechnung mit Lieferdatum/-zeitraum erstellt.')">
        <option value="service" @selected(old('content', 'service') === 'service')>{{ __('Leistung (Zeit) — Leistungsdatum') }}</option>
        <option value="material" @selected(old('content') === 'material')>{{ __('Material — Lieferdatum') }}</option>
        <option value="proforma" @selected(old('content') === 'proforma')>{{ __('Pro-forma — keine steuerliche Rechnung (eigener PF-Nummernkreis)') }}</option>
        <option value="down_payment" @selected(old('content') === 'down_payment')>{{ __('Abschlag — Teilentgelt vor Leistung (Anrechnung in der Schlussrechnung)') }}</option>
    </x-select-field>
    <div x-show="content !== 'down_payment'" class="contents">
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="from" to-name="to"
                      :from="old('from', $defaultFrom ?? '')" :to="old('to', $defaultTo ?? '')"
                      :from-label="__('Von')" :to-label="__('Bis')" />
    </div>
    <div x-show="content === 'down_payment'" x-cloak class="contents">
        <x-input-field name="dp_description" span="2"
                       :label="__('Leistungsbeschreibung (Abschlag)')" :value="old('dp_description', '')"
                       :hint="__('Erscheint als Pauschalposition auf der Abschlagsrechnung (§ 14 Abs. 5 UStG).')" />
        <x-input-field name="dp_amount" type="number" step="0.01" min="0.01"
                       :label="__('Abschlagsbetrag (netto)')" :value="old('dp_amount', '')" />
        <x-input-field name="dp_service_date" type="date"
                       :label="__('Voraussichtliches Leistungsdatum (optional)')" :value="old('dp_service_date', '')" />
    </div>
    <div x-show="content === 'service' || content === 'material'" class="contents">
        <x-checkbox-field name="mark_partial" span="2"
                          :label="__('Als Teilrechnung kennzeichnen')" :checked="(bool) old('mark_partial')"
                          :hint="__('Abrechnung eines fachlich abgrenzbaren Leistungsteils; Folge: weitere Teil- oder Schlussrechnung.')" />
    </div>
    <x-input-field name="payment_terms_days" type="number" min="0" max="365" span="2"
                   :label="__('Zahlungsziel (Tage)')" :value="old('payment_terms_days', 14)"
                   :hint="__('Steuert die Fälligkeit bei der Ausstellung (Standard: 14 Tage).')" />
</x-form-group>
</div>

<x-validation-errors />
