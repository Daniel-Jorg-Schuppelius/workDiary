{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _send_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mitteilung senden (in #entry-modal geladen). Variablen: $claim, $email --}}
<x-modal
    :title="__('club.fees.action.send')"
    :eyebrow="$claim->number"
    icon="outgoing_mail"
    tone="primary"
    :action="route('club.fees.claims.send', $claim)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.fees.action.send')">

    <x-form-group :legend="__('club.fees.card.dispatches')" icon="outgoing_mail" tone="primary" cols="1" :description="__('club.fees.hint.send')">
        <x-input-field name="email" type="email" :label="__('club.field.email')" required maxlength="255" :value="old('email', $email)" />
    </x-form-group>
</x-modal>
