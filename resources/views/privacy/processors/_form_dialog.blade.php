{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anlage-Dialog Dienstleister (in #entry-modal geladen). Variablen: $roles --}}
<x-modal
    :title="__('Neuer Dienstleister')"
    :eyebrow="__('Dienstleister & AVV')"
    icon="handshake"
    tone="primary"
    :action="route('dataprotection.processors.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-form-group :legend="__('Dienstleister')" icon="handshake" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Name')" :value="old('name')" required />
        <x-input-field name="role" :label="__('Rolle')">
            <select id="role" name="role" class="select select-bordered w-full">
                @foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>@endforeach
            </select>
        </x-input-field>
        <x-input-field name="contact" :label="__('Kontakt')" :value="old('contact')" />
        <x-input-field name="location" :label="__('Verarbeitungsort')" :value="old('location')" />
        <x-input-field name="third_country" :label="__('Drittlandtransfer')" span="2">
            <label class="flex items-center gap-2">
                <input type="hidden" name="third_country" value="0">
                <input type="checkbox" id="third_country" name="third_country" value="1" class="checkbox" @checked(old('third_country'))> {{ __('Drittlandtransfer') }}
            </label>
        </x-input-field>
        <x-input-field name="notes" :label="__('Notizen')" span="2">
            <textarea id="notes" name="notes" rows="3" class="textarea textarea-bordered w-full">{{ old('notes') }}</textarea>
        </x-input-field>
    </x-form-group>
</x-modal>
