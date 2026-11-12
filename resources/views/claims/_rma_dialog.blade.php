{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _rma_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Rücksendung ankündigen (Feature 072, MVP-250/1576). Erwartet: $claim, $stock (array|null).
     Lokaler Dialog (:embedded="false"), geöffnet via data-open-dialog. --}}
<x-modal
    id="claim-rma-create"
    :embedded="false"
    :title="__('Rücksendung ankündigen')"
    :eyebrow="__('Rückläufer (RMA)')"
    icon="assignment_return"
    tone="primary"
    :badge="$claim->number"
    :action="route('claims.rma.store', $claim)"
    method="POST"
    :submit-label="__('Rücksendung ankündigen')"
>
    <x-form-group :legend="__('claims.rma.group_return')" icon="assignment_return" tone="primary" cols="2">
        <x-input-field name="serial_no" id="claim-rma-create-serial-no" :label="__('Seriennummer (optional)')" maxlength="255" :value="$claim->serial_no" />
        <x-input-field name="expected_at" id="claim-rma-create-expected" type="date" :label="__('Erwartet am')" />
    </x-form-group>
    @if ($stock !== null)
        @include('claims._rma_stock_fields', ['claim' => $claim, 'rma' => null, 'prefix' => 'claim-rma-create', 'stock' => $stock, 'warehouseRequired' => false])
    @else
        <p class="text-sm text-muted">{{ __('claims.rma.no_stock_module') }}</p>
    @endif
</x-modal>
