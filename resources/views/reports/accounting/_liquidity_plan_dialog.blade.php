{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _liquidity_plan_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Planposition anlegen (MVP-984). Variablen: $recurrences
--}}
<x-modal
    :title="__('accounting.liquidity_plan.action.add')"
    icon="edit_calendar"
    :action="route('reports.accounting.liquidity-plan.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    <x-input-field name="label" required maxlength="200" :label="__('accounting.liquidity_plan.field.label')" :value="old('label')" />
    <x-select-field name="direction" :label="__('accounting.liquidity_plan.field.direction')" required>
        <option value="out" @selected(old('direction', 'out') === 'out')>{{ __('accounting.reports.forecast.column.outflow') }}</option>
        <option value="in" @selected(old('direction') === 'in')>{{ __('accounting.reports.forecast.column.inflow') }}</option>
    </x-select-field>
    <x-input-field name="planned_amount" type="number" min="0.01" step="0.01" inputmode="decimal" required
                   :label="__('accounting.liquidity_plan.field.planned_amount')" :value="old('planned_amount')" />
    <x-input-field name="starts_on" type="date" required :label="__('accounting.liquidity_plan.field.starts_on')" :value="old('starts_on', now()->toDateString())" />
    <x-select-field name="recurrence" :label="__('accounting.liquidity_plan.field.recurrence')" required>
        @foreach ($recurrences as $recurrence)
            <option value="{{ $recurrence->value }}" @selected(old('recurrence', 'once') === $recurrence->value)>{{ $recurrence->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="ends_on" type="date" :label="__('accounting.liquidity_plan.field.ends_on')" :hint="__('accounting.liquidity_plan.hint.ends_on')" :value="old('ends_on')" />
    <x-input-field name="note" maxlength="500" :label="__('accounting.ledger.field.note')" :value="old('note')" />
</x-modal>
