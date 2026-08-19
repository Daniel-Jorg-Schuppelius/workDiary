{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php use App\Enums\Access\AccessMediumType; @endphp

<x-modal
    :title="__('Zutrittsmedium anlegen')"
    :eyebrow="__('Zutrittsmedien')"
    icon="key"
    tone="primary"
    :action="route('access-media.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-form-group cols="2">
        <x-select-field name="type" :label="__('Typ')" required>
            @foreach (AccessMediumType::cases() as $type)
                <option value="{{ $type->value }}">{{ $type->label() }}</option>
            @endforeach
        </x-select-field>
        {{-- Die Nummer wird nur gehasht gespeichert; sichtbar bleiben die
             letzten vier Stellen. --}}
        <x-input-field name="number" :label="__('Mediennummer')" required
                       :hint="__('Wird nur gehasht gespeichert — sichtbar bleiben die letzten 4 Stellen.')" />
        <x-input-field name="label" :label="__('Bezeichnung')" placeholder="{{ __('z. B. Haupteingang Nord') }}" />
        <x-select-field name="site" :label="__('Objekt / Standort')">
            <option value="">{{ __('— keiner —') }}</option>
            @foreach ($sites as $site)
                <option value="{{ $site->sqid }}">{{ $site->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="system_name" :label="__('Anlage / System')" span="2"
                       :hint="__('Die Sperr-Aufgabe bei Verlust nennt diese Anlage.')" />
        <x-textarea-field name="notes" :label="__('Notizen')" rows="2" span="2"></x-textarea-field>
    </x-form-group>
</x-modal>
