{{--
  Created on   : Wed Aug 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abrechnungslauf anlegen (Feature 146). Variablen: $defaultStart, $defaultEnd, $currencies
--}}
<x-modal
    :title="__('commission.action.create_run')"
    :eyebrow="__('commission.page.runs')"
    icon="event_repeat"
    tone="primary"
    size="md"
    :action="route('commission-runs.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('commission.action.create_run')">

    <x-form-group :legend="__('commission.group.period')" icon="event" tone="primary" cols="2">
        <x-input-field name="period" :label="__('commission.field.period')" maxlength="20"
                       :hint="__('commission.hint.period')"
                       :value="old('period', $defaultStart->format('Y-m'))" />
        <x-select-field name="currency" :label="__('commission.field.currency')" required :hint="__('commission.hint.currency')">
            @foreach ($currencies as $currency)
                <option value="{{ $currency->value }}" @selected(old('currency', 'EUR') === $currency->value)>{{ $currency->value }}</option>
            @endforeach
        </x-select-field>
        <x-date-range class="md:col-span-2" layout="split" form-control required
                      from-name="period_start" to-name="period_end" type="date"
                      :from="old('period_start', $defaultStart->toDateString())"
                      :to="old('period_end', $defaultEnd->toDateString())"
                      :from-label="__('commission.field.period_start')"
                      :to-label="__('commission.field.period_end')" />
    </x-form-group>
</x-modal>
