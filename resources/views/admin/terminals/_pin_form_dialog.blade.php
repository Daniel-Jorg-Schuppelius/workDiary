{{--
  Created on   : Thu Sep 17 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _pin_form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Terminal-PIN setzen (MVP-803). Variablen: $users (mit Personalnummer) --}}
<x-modal
    :title="__('terminal.pin.action.set')"
    :eyebrow="__('terminal.pin.heading')"
    icon="pin"
    tone="primary"
    :action="route('admin.terminals.pins.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('terminal.pin.action.set')">

    <x-form-group :legend="__('terminal.pin.heading')" :description="__('terminal.pin.help.dialog')" icon="pin" tone="primary" cols="2">
        <x-select-field name="user" :label="__('terminal.badge.user')" required span="2" :hint="__('terminal.pin.help.personnel_number')">
            <option value="">—</option>
            @foreach ($users as $user)
                <option value="{{ $user->sqid }}">{{ $user->name }} ({{ $user->personnel_number }})</option>
            @endforeach
        </x-select-field>
        <x-input-field name="pin" type="password" inputmode="numeric" autocomplete="new-password" required minlength="4" maxlength="8" pattern="[0-9]{4,8}" :label="__('terminal.pin.field.pin')" />
        <x-input-field name="pin_confirmation" type="password" inputmode="numeric" autocomplete="new-password" required minlength="4" maxlength="8" pattern="[0-9]{4,8}" :label="__('terminal.pin.field.pin_confirmation')" />
    </x-form-group>
</x-modal>
