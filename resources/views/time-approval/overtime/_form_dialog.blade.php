{{--
  Created on   : Wed Aug 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Erwartet: $scopeDate, $isDialog
--}}
@php
    $isDialog = $isDialog ?? false;
@endphp

<x-modal
    :title="__('Überstunden beantragen')"
    :eyebrow="__('Überstunden-Antrag')"
    icon="more_time"
    tone="primary"
    :action="route('overtime.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Einreichen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('overtime.create') }}">
    @endif

    <x-form-group :legend="__('Antragsdaten')" icon="more_time" tone="primary" cols="2">
        <x-input-field name="scope_date" type="date" :label="__('Bezugsdatum')"
                       :value="old('scope_date', $scopeDate->format('Y-m-d'))" required />
        <x-input-field name="minutes" type="number" :label="__('Mehrarbeit (Minuten)')"
                       min="1" max="1440" :value="old('minutes')" required />
        <x-textarea-field name="reason" span="2" :label="__('Betriebliche Veranlassung (≥ 20 Zeichen)')"
                          rows="3" minlength="20" maxlength="4000" :value="old('reason')" required />
    </x-form-group>
</x-modal>
