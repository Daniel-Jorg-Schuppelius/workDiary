{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _chargeback_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Rücklastschrift erfassen (in #entry-modal geladen). Variablen: $account, $payment --}}
<x-modal
    :title="__('club.fees.action.chargeback')"
    :eyebrow="$account->name . ' · ' . $payment->amount->format() . ($payment->claim ? ' · ' . $payment->claim->number : '')"
    icon="undo"
    tone="warning"
    :action="route('club.fees.payments.chargeback', [$account, $payment])"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.fees.action.chargeback')">

    <x-form-group :legend="__('club.fees.label.chargeback')" icon="undo" tone="warning" cols="2" :description="__('club.fees.hint.chargeback')">
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" span="2" :value="old('reason')" />
        <x-input-field name="bank_fee" inputmode="decimal" :label="__('club.fees.field.bank_fee')" maxlength="20" :value="old('bank_fee')" :hint="__('club.fees.hint.bank_fee')" />
    </x-form-group>
</x-modal>
