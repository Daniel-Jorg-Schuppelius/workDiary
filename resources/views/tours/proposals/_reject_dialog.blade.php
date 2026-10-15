{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reject_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $proposal --}}
<x-modal
    :title="__('Vorschlag verwerfen')"
    :eyebrow="__('Tourvorschlag')"
    icon="close"
    tone="warning"
    :action="route('tour-proposals.reject', $proposal)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Verwerfen')">

    <x-form-group :legend="__('Grund')" icon="comment" tone="warning" cols="1">
        <x-textarea-field name="decision_note" :label="__('Warum wird der Vorschlag verworfen?')" rows="3" required maxlength="1000" :value="old('decision_note')" />
    </x-form-group>

    <x-validation-errors />
</x-modal>
