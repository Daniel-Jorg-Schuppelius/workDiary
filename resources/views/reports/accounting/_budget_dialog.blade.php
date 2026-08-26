{{--
  Created on   : Tue Aug 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _budget_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Budget eines Kontos (Feature 142, MVP-709): Jahreswert ODER zwölf
  Monatswerte — der gewählte Modus entscheidet, was gespeichert wird.
  Variablen: $account, $year, $costCenter, $months, $row
--}}
@php
    $mode = old('mode', $row['mode'] ?? \App\Services\Accounting\AccountingBudgetService::MODE_YEAR);
@endphp

<x-modal
    :title="__('accounting.budget.action.edit')"
    :eyebrow="$account->displayLabel() . ' · ' . $year . ($costCenter !== null ? ' · ' . $costCenter->code : '')"
    icon="edit_calendar"
    tone="primary"
    :action="route('reports.accounting.budget.update', $account)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('accounting.budget.action.save')">

    <input type="hidden" name="fiscal_year" value="{{ $year }}">
    <input type="hidden" name="cost_center" value="{{ $costCenter?->sqid ?? '' }}">

    <div class="alert bg-info/10 border-info/30 text-sm text-base-content" role="note">
        <x-icon name="info" />
        <span>{{ __('accounting.budget.hint.mode') }} {{ __('accounting.budget.hint.sign') }}</span>
    </div>

    <x-form-group :legend="__('accounting.budget.column.year_value')" icon="calendar_today" tone="primary" cols="2">
        <label class="flex items-center gap-2 text-sm">
            <input type="radio" name="mode" value="{{ \App\Services\Accounting\AccountingBudgetService::MODE_YEAR }}" class="radio radio-primary radio-sm" @checked($mode === \App\Services\Accounting\AccountingBudgetService::MODE_YEAR)>
            {{ __('accounting.budget.mode.year') }}
        </label>
        <x-input-field name="year_amount" type="text" inputmode="decimal"
                       :label="__('accounting.budget.column.year_value')"
                       :value="old('year_amount', $row['year'] ?? '')" />
    </x-form-group>

    <x-form-group :legend="__('accounting.budget.mode.months')" icon="date_range" tone="ghost" cols="4">
        <label class="flex items-center gap-2 text-sm sm:col-span-4">
            <input type="radio" name="mode" value="{{ \App\Services\Accounting\AccountingBudgetService::MODE_MONTHS }}" class="radio radio-primary radio-sm" @checked($mode === \App\Services\Accounting\AccountingBudgetService::MODE_MONTHS)>
            {{ __('accounting.budget.mode.months') }}
        </label>
        @foreach ($months as $month)
            <x-input-field :name="'months[' . $month->month . ']'" :id="'budget-month-' . $month->month" type="text" inputmode="decimal"
                           :label="$month->translatedFormat('M Y')"
                           :value="old('months.' . $month->month, $row['months'][$month->month] ?? '')" />
        @endforeach
    </x-form-group>

    <x-input-field name="note" :label="__('accounting.budget.column.note')" maxlength="191"
                   :value="old('note', $row['note'] ?? '')" />
</x-modal>
