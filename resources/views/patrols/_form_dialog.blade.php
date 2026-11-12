{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Route anlegen bzw. bearbeiten (MVP-1611), in #entry-modal geladen. Variablen: $route (null = neu), $sites, $users --}}
<x-modal
    :title="$route === null ? __('Rundgangs-Route anlegen') : __('patrol.route.edit_title')"
    :eyebrow="__('Wächterrundgänge')"
    icon="route"
    tone="primary"
    :action="$route === null ? route('patrols.store') : route('patrols.update', $route)"
    :method="$route === null ? 'POST' : 'PUT'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$route === null ? __('Anlegen') : __('Speichern')">

    <x-input-field name="name" :label="__('Bezeichnung')" :value="old('name', $route?->name)" required maxlength="160"
                   placeholder="{{ __('z. B. Revierfahrt Gewerbepark Nacht') }}" />
    <x-select-field name="site" :label="__('Objekt / Standort')">
        <option value="">{{ __('— keiner —') }}</option>
        @foreach ($sites as $site)
            <option value="{{ $site->sqid }}" @selected(old('site', $route?->site?->sqid) === $site->sqid)>{{ $site->name }}</option>
        @endforeach
    </x-select-field>
    <x-select-field name="responsible" :label="__('patrol.route.responsible')" :hint="__('patrol.route.responsible_hint')">
        <option value="">{{ __('— keiner —') }}</option>
        @foreach ($users as $user)
            <option value="{{ $user->sqid }}" @selected(old('responsible', $route?->responsible?->sqid) === $user->sqid)>{{ $user->name }}</option>
        @endforeach
    </x-select-field>
</x-modal>
