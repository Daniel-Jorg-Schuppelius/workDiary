{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

<x-modal
    :title="__('Rundgangs-Route anlegen')"
    :eyebrow="__('Wächterrundgänge')"
    icon="route"
    tone="primary"
    :action="route('patrols.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-input-field name="name" :label="__('Bezeichnung')" required placeholder="{{ __('z. B. Revierfahrt Gewerbepark Nacht') }}" />
    <x-select-field name="site" :label="__('Objekt / Standort')">
        <option value="">{{ __('— keiner —') }}</option>
        @foreach ($sites as $site)
            <option value="{{ $site->sqid }}">{{ $site->name }}</option>
        @endforeach
    </x-select-field>
</x-modal>
