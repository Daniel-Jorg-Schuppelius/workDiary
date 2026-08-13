{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@php
    $isDialog = $isDialog ?? false;
@endphp

<x-modal
    :title="__('Rollplan anlegen')"
    :eyebrow="__('Rollplan')"
    icon="event_repeat"
    tone="primary"
    :action="route('admin.shift-rotations.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('admin.shift-rotations.create') }}">
    @endif

    <x-form-group :legend="__('Rhythmus')" icon="event_repeat" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" :value="old('name')" required span="2" />
        <x-input-field name="weeks_count" type="number" :label="__('Anzahl Wochen (1–26)')"
                       min="1" max="26" :value="old('weeks_count', 2)" required />
    </x-form-group>
</x-modal>
