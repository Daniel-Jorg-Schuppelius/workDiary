{{--
  Created on   : Sat Jul 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: neues Angebot (Feature 066, MVP-170) --}}
<x-modal
    :title="__('Neues Angebot')"
    :eyebrow="__('Angebot')"
    icon="request_quote"
    tone="primary"
    :action="route('quotes.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Entwurf anlegen')"
>
    <x-form-group :legend="__('Angebot')" icon="request_quote" tone="primary" cols="2">
        <x-select-field name="customer_id" :label="__('Kunde')" required span="2">
            <option value="">{{ __('-- bitte wählen --') }}</option>
            @foreach ($customers as $c)
                <option value="{{ $c->sqid }}" @selected((string) old('customer_id') === $c->sqid)>{{ $c->name }}</option>
            @endforeach
        </x-select-field>
        <x-project-select :label="__('Projekt (optional)')" :placeholder="__('ohne Projektbezug')" span="2"
            :projects="$projects" :selected="(string) old('project_id')"
            data-depends-on="customer_id" :data-parent="true" />
        <x-input-field name="valid_until" type="date" :label="__('Bindefrist (gültig bis)')" :value="old('valid_until')" />
        <x-textarea-field name="terms" :label="__('Bedingungen / Leistungsumfang (optional)')" rows="3" span="2">{{ old('terms') }}</x-textarea-field>
        @php
            $labourRule = \App\Models\Sales\Quote::labourCostDisclosureRule(auth()->user()?->organization);
        @endphp
        <x-select-field name="labour_cost_disclosure" :label="__('invoicing.labour_costs.override')" span="2" :hint="__('invoicing.labour_costs.override_hint')">
            <option value="">{{ __('invoicing.labour_costs.override_default', ['rule' => $labourRule->label()]) }}</option>
            <option value="1" @selected(old('labour_cost_disclosure') === '1')>{{ __('invoicing.labour_costs.override_on') }}</option>
            <option value="0" @selected(old('labour_cost_disclosure') === '0')>{{ __('invoicing.labour_costs.override_off') }}</option>
        </x-select-field>
    </x-form-group>

    <x-validation-errors />
</x-modal>
