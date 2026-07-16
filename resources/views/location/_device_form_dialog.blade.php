{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _device_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

<x-modal
    :title="__('Gerät verbinden')"
    :eyebrow="__('Standorterfassung')"
    icon="smartphone"
    tone="primary"
    size="md"
    :action="route('location.devices.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Token erzeugen')">

    <x-form-group :legend="__('Gerät')" icon="smartphone" tone="primary" cols="1">
        <x-input-field name="label" :label="__('Bezeichnung')" required maxlength="120"
                       :placeholder="__('z. B. Mein Diensthandy')" :value="old('label')" />
    </x-form-group>

    <p class="text-sm text-base-content/70">
        {{ __('Nach dem Erzeugen wird einmalig eine Push-URL für OwnTracks/Traccar angezeigt. Das Verbinden aktiviert deine Einwilligung.') }}
    </p>
</x-modal>
