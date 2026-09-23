{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dunning_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mahnstufe erstellen (in #entry-modal geladen). Variablen: $claim, $level, $email, $today --}}
<x-modal
    :title="__('club.fees.action.dun', ['level' => $level])"
    :eyebrow="$claim->number . ' · ' . $claim->openAmount()->format()"
    icon="notification_important"
    tone="warning"
    :action="route('club.fees.claims.dun', $claim)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.fees.action.dun', ['level' => $level])">

    <x-form-group :legend="__('club.fees.card.dunning')" icon="notification_important" tone="warning" cols="2" :description="__('club.fees.hint.dunning')">
        <x-input-field name="pay_until" type="date" :label="__('club.fees.field.pay_until')" :value="old('pay_until', $today->addDays(14)->toDateString())" />
        <x-input-field name="fee" inputmode="decimal" :label="__('club.fees.field.dunning_fee')" maxlength="20" :value="old('fee')" :hint="__('club.fees.hint.dunning_fee')" />
        <x-input-field name="note" :label="__('club.field.note')" maxlength="255" span="2" :value="old('note')" />
        <x-checkbox-field name="send_mail" :label="__('club.fees.field.send_mail')" :checked="(bool) old('send_mail', $email !== '')" />
        <x-input-field name="email" type="email" :label="__('club.field.email')" maxlength="255" :value="old('email', $email)" />
    </x-form-group>
</x-modal>
