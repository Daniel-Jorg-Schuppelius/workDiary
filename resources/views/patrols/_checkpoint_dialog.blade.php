{{--
  Created on   : Wed Nov 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _checkpoint_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kontrollpunkt bearbeiten (MVP-1611), in #entry-modal geladen. Variablen: $route, $checkpoint --}}
<x-modal
    :title="__('patrol.checkpoint.edit_title')"
    :eyebrow="$route->name"
    icon="location_on"
    tone="primary"
    :action="route('patrols.checkpoints.update', [$route, $checkpoint])"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">

    <x-input-field name="label" :label="__('Kontrollpunkt (Ort)')" :value="old('label', $checkpoint->label)" required maxlength="160" />
    <x-input-field type="number" name="expected_offset_minutes" :label="__('Soll ab Start (min)')"
                   :value="old('expected_offset_minutes', $checkpoint->expected_offset_minutes)" required min="0" max="1440" />
    <x-input-field type="number" name="tolerance_minutes" :label="__('Toleranz (min)')"
                   :value="old('tolerance_minutes', $checkpoint->tolerance_minutes)" required min="0" max="240"
                   :hint="__('patrol.checkpoint.edit_hint')" />
</x-modal>
