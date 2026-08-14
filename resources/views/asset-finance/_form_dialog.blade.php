{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: neue Leasing-/Finanzierungsakte (Feature 074, MVP-271) --}}
<x-modal
    :title="__('Neue Leasingakte')"
    :eyebrow="__('Leasing & Verträge')"
    icon="request_quote"
    tone="primary"
    :action="route('asset-finance.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Akte anlegen')"
>
    <x-form-group :legend="__('Vertrag')" icon="request_quote" tone="primary" cols="2">
        <x-select-field name="kind" :label="__('Vertragsart')" required>
            @foreach (\App\Enums\AssetFinance\AssetFinanceKind::cases() as $kind)
                <option value="{{ $kind->value }}" @selected(old('kind') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="partner_name" :label="__('Vertragspartner (Leasinggeber)')" :value="old('partner_name')" required />
        <x-select-field name="supplier_id" :label="__('Lieferant (optional)')">
            <option value="">{{ __('kein Lieferantenbezug') }}</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->sqid }}" @selected((string) old('supplier_id') === $s->sqid)>{{ $s->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="contract_no" :label="__('Vertragsnummer (extern)')" :value="old('contract_no')" />
        <x-date-range class="md:col-span-2" layout="split" form-control
                      from-name="starts_on" to-name="ends_on" type="date" fromId="af-starts-on" toId="af-ends-on"
                      :from-label="__('Beginn')" :to-label="__('Ende')"
                      :from="old('starts_on')" :to="old('ends_on')" />
        <x-input-field name="notice_period_days" type="number" min="0" :label="__('Kündigungsfrist (Tage)')" :value="old('notice_period_days')" />
        <x-select-field name="responsible_user_id" :label="__('Verantwortlich')">
            <option value="">{{ __('-- später zuweisen --') }}</option>
            @foreach ($users as $u)
                <option value="{{ $u->sqid }}" @selected((string) old('responsible_user_id') === $u->sqid)>{{ $u->name }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>

    <x-form-group :legend="__('Konditionen (vertraulich)')" icon="lock" tone="primary" cols="2">
        <x-select-field name="payment_rhythm" :label="__('Zahlungsrhythmus')" required>
            <option value="monthly" @selected(old('payment_rhythm', 'monthly') === 'monthly')>{{ __('monatlich') }}</option>
            <option value="quarterly" @selected(old('payment_rhythm') === 'quarterly')>{{ __('quartalsweise') }}</option>
            <option value="yearly" @selected(old('payment_rhythm') === 'yearly')>{{ __('jährlich') }}</option>
        </x-select-field>
        <x-input-field name="rate_amount" type="number" step="0.01" min="0" :label="__('Rate')" :value="old('rate_amount')" />
        <x-input-field name="special_payment" type="number" step="0.01" min="0" :label="__('Sonderzahlung')" :value="old('special_payment')" />
        <x-input-field name="residual_value" type="number" step="0.01" min="0" :label="__('Restwertannahme')" :value="old('residual_value')" />
        <x-input-field name="purchase_option_amount" type="number" step="0.01" min="0" :label="__('Kaufoption (Betrag)')" :value="old('purchase_option_amount')" />
        <x-input-field name="insurance_note" :label="__('Versicherung')" :value="old('insurance_note')" />
    </x-form-group>

    <x-form-group :legend="__('Zuordnung')" icon="account_tree" tone="primary" cols="2">
        <x-select-field name="asset_ids[]" :label="__('Assets (Mehrfachauswahl)')" multiple size="5" span="2">
            @foreach ($assets as $a)
                <option value="{{ $a->sqid }}" @selected(collect(old('asset_ids', []))->contains($a->sqid))>{{ $a->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="cost_center_id" :label="__('Kostenstelle')">
            <option value="">{{ __('keine') }}</option>
            @foreach ($costCenters as $cc)
                <option value="{{ $cc->sqid }}" @selected((string) old('cost_center_id') === $cc->sqid)>{{ $cc->code }} — {{ $cc->label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="project_id" :label="__('Projekt (optional)')">
            <option value="">{{ __('ohne Projektbezug') }}</option>
            @foreach ($projects as $p)
                <option value="{{ $p->sqid }}" @selected((string) old('project_id') === $p->sqid)>{{ $p->name }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="investment_case_id" :label="__('Investitionsakte (optional)')" span="2">
            <option value="">{{ __('keine Verknüpfung') }}</option>
            @foreach ($investmentCases as $ic)
                <option value="{{ $ic->sqid }}" @selected((string) old('investment_case_id') === $ic->sqid)>{{ $ic->title }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="notes" :label="__('Notizen')" rows="2" span="2">{{ old('notes') }}</x-textarea-field>
    </x-form-group>

    <p class="text-xs text-base-content/60">
        {{ __('Hinweis: WorkDiary führt die operative Leasingakte (B2B). Bilanzierung (HGB/IFRS 16), steuerliche Zurechnung und Verbraucherschutzpflichten (CCD II, ab 20.11.2026 für Verbraucherverträge mit Kaufoption) bleiben Sache des Rechnungswesens bzw. der Rechtsberatung.') }}
    </p>

    <x-validation-errors />
</x-modal>
