{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _cancel_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Forderung stornieren (in #entry-modal geladen). Variablen: $claim --}}
<x-modal
    :title="__('club.fees.action.cancel_claim')"
    :eyebrow="$claim->number"
    icon="block"
    tone="warning"
    :action="route('club.fees.claims.cancel', $claim)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.fees.action.cancel_claim')">

    <x-form-group :legend="__('club.attendance.field.reason')" icon="block" tone="warning" cols="1" :description="__('club.fees.hint.cancel_claim')">
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" :value="old('reason')" />
    </x-form-group>
</x-modal>
