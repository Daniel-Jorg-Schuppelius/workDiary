{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _indexation_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wertsicherung nach VPI (MVP-952). Erwartet: $contract --}}
<x-modal
    :title="__('contract.indexation.title')"
    :eyebrow="$contract->number"
    icon="trending_up"
    tone="primary"
    :action="route('contracts.indexation.update', $contract)"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('contract.indexation.save')"
>
    <x-form-group :legend="__('contract.indexation.section.base')" icon="trending_up" tone="primary" cols="2">
        <x-input-field name="indexation_base_value" type="number" step="0.1" min="1" :label="__('contract.indexation.field.base_value')" :value="old('indexation_base_value', $contract->indexation_base_value)" :hint="__('contract.indexation.hint.base_value')" />
        <x-input-field name="indexation_base_period" type="month" :label="__('contract.indexation.field.base_period')" :value="old('indexation_base_period', $contract->indexation_base_period_on?->format('Y-m'))" />
        <x-input-field name="indexation_threshold_percent" type="number" step="0.01" min="0" max="100" :label="__('contract.indexation.field.threshold')" :value="old('indexation_threshold_percent', $contract->indexation_threshold_percent)" :hint="__('contract.indexation.hint.threshold')" />
        <x-input-field name="indexation_pass_through_percent" type="number" step="0.01" min="0" max="100" :label="__('contract.indexation.field.pass_through')" :value="old('indexation_pass_through_percent', $contract->indexation_pass_through_percent)" :hint="__('contract.indexation.hint.pass_through')" />
    </x-form-group>
    <p class="text-xs text-muted">{{ __('contract.indexation.disclaimer') }}</p>
</x-modal>
