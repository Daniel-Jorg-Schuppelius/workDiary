{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _closure_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sperrzeit setzen (in #entry-modal geladen): betroffene Belegungen werden markiert, nicht gelöscht. Variablen: $resource, $formTz. --}}
<x-modal
    :title="__('club.resources.action.close')"
    :eyebrow="$resource->name"
    icon="block"
    tone="warning"
    :action="route('club.resources.close', $resource)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.resources.action.close')">
    <x-form-group :legend="__('club.resources.card.closures')" icon="block" tone="warning" cols="1" :description="__('club.resources.hint.closure')">
        <x-date-range layout="split" form-control required type="datetime-local" from-name="starts_at" to-name="ends_at"
                      :from-label="__('club.events.field.starts')" :to-label="__('club.events.field.ends')"
                      :from="old('starts_at')" :to="old('ends_at')" />
        <input type="hidden" name="timezone" value="{{ old('timezone', $formTz) }}">
        <x-input-field name="reason" :label="__('club.field.reason')" required maxlength="255" :value="old('reason')" :hint="__('club.resources.hint.closure_reason')" />
    </x-form-group>
</x-modal>
