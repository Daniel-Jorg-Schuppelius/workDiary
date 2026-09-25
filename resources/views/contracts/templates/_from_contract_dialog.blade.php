{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _from_contract_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Vertrag als Vorlage speichern (MVP-893). Übernommen werden
     Art, Laufzeit, Kündigung, Verlängerung und die Pflichten relativ zum Beginn. --}}
<x-modal :title="__('contract.template.save_title')" :eyebrow="$contract->number . ' · ' . $contract->title" icon="library_add" tone="primary"
         :action="route('contracts.templates.from-contract', $contract)" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="__('contract.template.save')">
    <p class="text-sm text-muted">{{ __('contract.template.save_hint') }}</p>
    <x-input-field name="name" :label="__('contract.template.name')" :value="old('name', $contract->title)" required maxlength="180" />
</x-modal>
