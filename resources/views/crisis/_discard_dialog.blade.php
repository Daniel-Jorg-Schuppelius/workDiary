{{--
  Created on   : Mon Nov 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _discard_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: gemeldeten Krisenfall als Fehlmeldung verwerfen, Begründung Pflicht (MVP-1517) --}}
<x-modal
    :title="__('Fehlmeldung verwerfen')"
    :eyebrow="$case->title"
    icon="delete"
    tone="error"
    :action="route('crisis.discard', $case)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Verwerfen')"
>
    <x-form-group :legend="__('Begründung')" icon="edit_note" tone="error" cols="1">
        <x-input-field name="reason" :label="__('Begründung (Pflicht)')" required />
    </x-form-group>
</x-modal>
