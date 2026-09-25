{{--
  Created on   : Fri Sep 25 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _dunning_block_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Mahnsperre setzen (MVP-874, in #entry-modal geladen). Variablen: $invoice --}}
<x-modal
    :title="__('finance.dunning.action_block')"
    :eyebrow="(string) $invoice->number"
    icon="notifications_off"
    tone="warning"
    :action="route('invoices.dunning-block', $invoice)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    submit-class="btn-warning"
    :submit-label="__('finance.dunning.action_block')">

    <x-form-group :legend="__('finance.dunning.block_legend')" icon="notifications_off" tone="warning" cols="1" :description="__('finance.dunning.confirm_block', ['nr' => $invoice->number])">
        <x-input-field name="reason" :label="__('finance.dunning.block_reason')" maxlength="255" required :value="old('reason')" :hint="__('finance.dunning.block_reason_hint')" />
    </x-form-group>
</x-modal>
