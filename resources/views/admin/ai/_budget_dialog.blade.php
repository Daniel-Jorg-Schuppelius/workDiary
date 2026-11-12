{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _budget_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Monatsbudget je Familie (Feature 025, MVP-399). Leer = Vorgabe des Betreibers. --}}
<x-modal
    :title="__('ai.budget.title')"
    :eyebrow="__('ai.usage.title')"
    icon="savings"
    tone="primary"
    :action="route('admin.ai.budget.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    <x-form-group :legend="__('ai.budget.legend')" icon="savings" tone="primary" cols="1" :description="__('ai.budget.description')">
        @foreach ($families as $family)
            @php
                $default = config('ai.budget.monthly_units.' . $family->value);
            @endphp
            <x-input-field :name="'monthly_units[' . $family->value . ']'" :id="'ai-budget-' . $family->value"
                           type="number" min="0" step="1" inputmode="numeric"
                           :error="'monthly_units.' . $family->value"
                           :label="__('ai.budget.field.' . $family->value)"
                           :value="old('monthly_units.' . $family->value, $stored[$family->value] ?? '')"
                           :placeholder="$default === null || $default === '' ? __('ai.usage.unlimited') : __('settings.placeholder_default', ['value' => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((int) $default, 0, withThousandsSeparator: true)])" />
        @endforeach
    </x-form-group>
</x-modal>
