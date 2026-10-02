{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Kopf des Aufmaßblatts (MVP-1058). Variablen: $takeoff, $carrierType, $carrierId --}}
@php
    $isEdit = $takeoff->exists;
@endphp
<x-modal
    :title="$isEdit ? $takeoff->title : __('takeoff.action.create')"
    icon="straighten"
    tone="primary"
    :action="$isEdit ? route('takeoffs.update', $takeoff) : route('takeoffs.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :submit-label="__('Speichern')"
    size="md">
    @unless ($isEdit)
        <input type="hidden" name="carrier_type" value="{{ $carrierType }}">
        <input type="hidden" name="carrier_id" value="{{ $carrierId }}">
    @endunless
    <x-form-group :legend="__('takeoff.title')" icon="straighten" tone="primary" cols="2">
        <x-input-field name="title" :label="__('takeoff.field.title')" required maxlength="200" span="2" :value="old('title', $takeoff->title)" />
        <x-input-field name="measured_on" type="date" :label="__('takeoff.field.measured_on')" :value="old('measured_on', $takeoff->measured_on?->format('Y-m-d'))" />
        <x-textarea-field name="note" :label="__('takeoff.field.note')" rows="3" span="2">{{ old('note', $takeoff->note) }}</x-textarea-field>
    </x-form-group>
    <x-validation-errors />
</x-modal>
