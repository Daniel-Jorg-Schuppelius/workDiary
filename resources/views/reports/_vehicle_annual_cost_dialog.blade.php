{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _vehicle_annual_cost_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Sonstige Jahreskosten eines Fahrzeugs (MVP-993). Variablen: $vehicle, $year, $cost (VehicleAnnualCost|null)
--}}
<x-modal :title="__('Sonstige Jahreskosten')" :eyebrow="$vehicle->displayName() . ' · ' . $year" icon="euro" tone="primary"
         :action="route('reports.logbook-comparison.costs', $vehicle)" method="POST"
         :form-data="['data-entry-form' => '']" :submit-label="__('Speichern')">
    <input type="hidden" name="year" value="{{ $year }}">
    <p class="text-sm text-muted">{{ __('Leasing, Versicherung, Kfz-Steuer, Wartung, Reparaturen und Abschreibung — Energie kommt aus den Tank- und Ladebelegen.') }}</p>
    <x-input-field name="cost_amount" type="number" step="0.01" min="0" inputmode="decimal" required
                   :label="__('Betrag (€)')" :value="old('cost_amount', $cost?->cost_amount?->getAmount())" />
    <x-input-field name="note" maxlength="255" :label="__('Hinweis')" :value="old('note', $cost?->note)" />
</x-modal>
