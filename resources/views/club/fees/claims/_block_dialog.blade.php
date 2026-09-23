{{--
  Created on   : Wed Sep 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _block_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mahnsperre setzen (in #entry-modal geladen). Variablen: $claim --}}
<x-modal
    :title="__('club.fees.action.block_dunning')"
    :eyebrow="$claim->number"
    icon="pause_circle"
    tone="warning"
    :action="route('club.fees.claims.dunning.block', $claim)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('club.fees.action.block_dunning')">

    <x-form-group :legend="__('club.attendance.field.reason')" icon="pause_circle" tone="warning" cols="1" :description="__('club.fees.hint.block_dunning')">
        <x-input-field name="reason" :label="__('club.attendance.field.reason')" required maxlength="255" :value="old('reason')" />
    </x-form-group>
</x-modal>
