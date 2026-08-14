{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php $skipStatusControls = $skipStatusControls ?? false; @endphp

{{-- Shared form fields for Qualification --}}

<x-form-group :legend="__('Stammdaten')" icon="school" tone="primary" cols="2">
    <x-input-field name="name" :label="__('Name')" required maxlength="255" autofocus :value="old('name', $qualification?->name)" />

    <x-input-field name="abbreviation" :label="__('Kürzel')" maxlength="20" :value="old('abbreviation', $qualification?->abbreviation)" />

    <x-textarea-field name="description" :label="__('Beschreibung')" rows="3" span="2" :value="old('description', $qualification?->description)" />

    @unless ($skipStatusControls)
    <x-checkbox-field name="is_active" :label="__('Aktiv')" :checked="old('is_active', $qualification?->is_active ?? true)" :toggle="false" span="2" />
    @endunless
</x-form-group>
