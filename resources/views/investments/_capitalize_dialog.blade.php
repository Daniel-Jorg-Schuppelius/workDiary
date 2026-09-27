{{--
  Created on   : Sat Sep 26 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _capitalize_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: genehmigte Investition als Anlage übernehmen (MVP-909). --}}
<x-modal :title="__('investment.capitalize.title')" :eyebrow="$case->title" icon="inventory" tone="primary"
         :action="route('investments.capitalize.store', $case)" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="__('investment.capitalize.submit')">
    <p class="text-sm text-muted">{{ __('investment.capitalize.hint') }}</p>
    <x-input-field name="name" :label="__('investment.capitalize.name')" :value="old('name', $preset['name'])" required maxlength="255" />
    <x-input-field name="acquired_on" type="date" :label="__('investment.capitalize.acquired_on')" :value="old('acquired_on', $preset['acquired_on'])" required />
    <x-input-field name="acquisition_cost" type="number" step="0.01" min="0.01" :label="__('investment.capitalize.cost')" :value="old('acquisition_cost', $preset['acquisition_cost'])" required />
    <x-input-field name="useful_life_months" type="number" min="1" max="1200" :label="__('investment.capitalize.life')" :value="old('useful_life_months', $preset['useful_life_months'])" required />
</x-modal>
