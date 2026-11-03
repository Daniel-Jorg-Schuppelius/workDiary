{{--
  Created on   : Tue Nov 03 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Variablen: $profiles, $defaultYear, $defaultMonth, $teamUsers --}}
<x-modal
    :title="__('Export erstellen')"
    :eyebrow="__('Zeit-Exporte')"
    icon="play_arrow"
    tone="primary"
    :action="route('exports.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Erstellen')">

    <p class="text-sm text-muted mb-3">{{ __('Genehmigte Monatsfreigaben in einen Lohnabrechnungs-Export zusammenfassen.') }}</p>

    {{-- Die Personenwahl erscheint nur beim Scope „Einzelne Person“ (reveal statt Inline-Skript, Dialog-Standard). --}}
    <div class="grid gap-4 sm:grid-cols-2" x-data="reveal(@js(old('scope', 'organization')))">
        <x-input-field name="year" type="number" :label="__('Jahr')" min="2000" max="2999" :value="old('year', $defaultYear)" required />
        <x-select-field name="month" :label="__('Monat')" required>
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected((int) old('month', $defaultMonth) === $m)>
                    {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                </option>
            @endfor
        </x-select-field>
        <x-select-field name="profile" :label="__('Profil')" required>
            @foreach ($profiles as $key => $label)
                <option value="{{ $key }}" @selected(old('profile') === $key)>{{ $label }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="scope" :label="__('Scope')" required x-model="value">
            <option value="organization" @selected(old('scope', 'organization') === 'organization')>{{ __('Gesamte Organisation') }}</option>
            <option value="user" @selected(old('scope') === 'user')>{{ __('Einzelne Person') }}</option>
        </x-select-field>
        <div class="sm:col-span-2" x-show="is('user')">
            <x-select-field name="scope_user_id" :label="__('Mitarbeiter:in')">
                <option value="">{{ __('— bitte wählen —') }}</option>
                @foreach ($teamUsers as $u)
                    <option value="{{ $u->sqid }}" @selected((string) old('scope_user_id') === $u->sqid)>{{ $u->name }}</option>
                @endforeach
            </x-select-field>
        </div>
    </div>
</x-modal>
