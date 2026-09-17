{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog Dienstleister: Anlegen UND Bearbeiten (MVP-798, Befund C1-08).
     Variablen: $roles, optional $processor + $isEdit. --}}
@php
    $isEdit = $isEdit ?? false;
    $processor = $processor ?? null;
    $wert = fn (string $feld, $vorgabe = null) => old($feld, $processor?->{$feld} ?? $vorgabe);
@endphp
<x-modal
    :title="$isEdit ? __('Dienstleister bearbeiten') : __('Neuer Dienstleister')"
    :eyebrow="__('Dienstleister & AVV')"
    icon="handshake"
    tone="primary"
    :action="$isEdit ? route('dataprotection.processors.update', $processor) : route('dataprotection.processors.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('Speichern') : __('Anlegen')">

    <x-form-group :legend="__('Dienstleister')" icon="handshake" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" :value="$wert('name')" required />
        <x-input-field name="role" :label="__('Rolle')">
            <select id="role" name="role" class="select select-bordered w-full">
                @foreach ($roles as $r)<option value="{{ $r->value }}" @selected($wert('role') === $r->value)>{{ $r->label() }}</option>@endforeach
            </select>
        </x-input-field>
        <x-input-field name="contact" :label="__('Kontakt')" :value="$wert('contact')" />
        <x-input-field name="location" :label="__('Verarbeitungsort')" :value="$wert('location')" />
        <x-input-field name="third_country" :label="__('Drittlandtransfer')" span="2">
            <label class="flex items-center gap-2">
                <input type="hidden" name="third_country" value="0">
                <input type="checkbox" id="third_country" name="third_country" value="1" class="checkbox" @checked($wert('third_country'))> {{ __('Drittlandtransfer') }}
            </label>
        </x-input-field>
        <x-input-field name="notes" :label="__('Notizen')" span="2">
            <textarea id="notes" name="notes" rows="3" class="textarea textarea-bordered w-full">{{ $wert('notes') }}</textarea>
        </x-input-field>
    </x-form-group>
</x-modal>
