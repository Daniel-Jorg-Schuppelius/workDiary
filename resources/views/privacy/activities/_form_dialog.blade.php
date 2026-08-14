{{--
  Created on   : Wed Jun 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Anlage-Dialog Verarbeitungstätigkeit (in #entry-modal geladen). Variablen: $roles --}}
<x-modal
    :title="__('Neue Verarbeitungstätigkeit')"
    :eyebrow="__('Verzeichnis von Verarbeitungstätigkeiten')"
    icon="fact_check"
    tone="primary"
    :action="route('dataprotection.activities.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">

    <x-form-group :legend="__('Tätigkeit')" icon="fact_check" tone="primary" cols="2">
        <x-input-field name="name" :label="__('Bezeichnung')" :value="old('name')" required />
        <x-input-field name="controller_role" :label="__('Verantwortungsrolle')">
            <select id="controller_role" name="controller_role" class="select select-bordered w-full">
                @foreach ($roles as $r)
                    <option value="{{ $r->value }}" @selected(old('controller_role') === $r->value)>{{ $r->label() }}</option>
                @endforeach
            </select>
        </x-input-field>
        <x-input-field name="purpose" :label="__('Zweck der Verarbeitung')" span="2">
            <textarea id="purpose" name="purpose" rows="2" class="textarea textarea-bordered w-full">{{ old('purpose') }}</textarea>
        </x-input-field>
        <x-input-field name="area" :label="__('Fachbereich (optional)')" :value="old('area')" />
    </x-form-group>

    @include('privacy.activities._payload_fields')
</x-modal>
