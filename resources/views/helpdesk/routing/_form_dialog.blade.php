{{--
  Created on   : Mon Oct 05 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Routing-Regel bearbeiten (Feature 065, MVP-153). Jede Speicherung erhöht
  die Version der Regel.
--}}
<x-modal
    :title="__('Regel bearbeiten')"
    :eyebrow="__('Ticket-Routing') . ' · v' . $rule->version"
    icon="alt_route"
    :action="route('helpdesk.routing.update', $rule)"
    method="PATCH"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')"
>
    {{-- Eigene ids: Die Seite darunter trägt dieselben Feldnamen im Anlegen-Formular. --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <x-input-field name="name" id="rule-edit-name" :label="__('Name')" required maxlength="120"
                       :value="old('name', $rule->name)" />
        <x-input-field name="position" id="rule-edit-position" type="number" :label="__('Position')" required min="1" max="999"
                       :value="old('position', $rule->position)" />
    </div>
    <x-textarea-field name="conditions" id="rule-edit-conditions" :label="__('Bedingungen (JSON)')" rows="5" required
                      class="font-mono text-xs">{{ old('conditions', $conditions) }}</x-textarea-field>
    <x-textarea-field name="actions" id="rule-edit-actions" :label="__('Aktionen (JSON)')" rows="5" required
                      class="font-mono text-xs">{{ old('actions', $actions) }}</x-textarea-field>
    <x-checkbox-field name="active" id="rule-edit-active" :label="__('Aktiv')"
                      :checked="(bool) old('active', $rule->active)" />
</x-modal>
