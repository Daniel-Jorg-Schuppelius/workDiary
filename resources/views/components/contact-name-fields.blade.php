{{--
  Created on   : Mon Oct 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : contact-name-fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Ansprechpartner mit Anrede und Namensteilen (MVP-1136); `contact_name` bildet das Modell daraus. --}}
@props(['model' => null])

@php
    $salutation = old('contact_salutation', $model?->contact_salutation?->value);
@endphp

<x-form-group :legend="__('Ansprechpartner')" icon="person" tone="ghost" cols="2">
    <x-select-field name="contact_salutation" :label="__('stammdaten.contact.salutation')">
        <option value="">—</option>
        @foreach (\App\Enums\Stammdaten\ContactSalutation::cases() as $case)
            <option value="{{ $case->value }}" @selected($salutation === $case->value)>{{ $case->label() }}</option>
        @endforeach
    </x-select-field>
    <x-input-field name="contact_title" :label="__('Titel')" maxlength="64" :value="old('contact_title', $model?->contact_title)" />
    <x-input-field name="contact_first_name" :label="__('Vorname')" maxlength="128" :value="old('contact_first_name', $model?->contact_first_name)" />
    <x-input-field name="contact_middle_names" :label="__('Weitere Vornamen')" maxlength="128" :value="old('contact_middle_names', $model?->contact_middle_names)" />
    <x-input-field name="contact_last_name" span="2" :label="__('Nachname')" maxlength="128" :value="old('contact_last_name', $model?->contact_last_name)" />
</x-form-group>
