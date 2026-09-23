{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _correction_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Korrektur als verknüpfte Forderung (in #entry-modal geladen). Variablen: $claim --}}
<x-modal
    :title="__('club.fees.action.correction')"
    :eyebrow="$claim->number"
    icon="difference"
    tone="primary"
    :action="route('club.fees.claims.correction.store', $claim)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('club.action.save')">

    <x-form-group :legend="__('club.fees.card.corrections')" icon="difference" tone="primary" cols="2" :description="__('club.fees.hint.correction')">
        <x-input-field name="amount" inputmode="decimal" :label="__('club.fees.field.amount')" required maxlength="20" :value="old('amount')" :hint="__('club.fees.hint.correction_amount')" />
        <x-input-field name="label" :label="__('club.fees.field.position')" required maxlength="160" :value="old('label')" />
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" span="2" :value="old('reason')" />
    </x-form-group>
</x-modal>
