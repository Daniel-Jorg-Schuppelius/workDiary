{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _rma_receive_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Wareneingang eines Rückläufers (Feature 072, MVP-250/1576). Erwartet: $claim, $rma, $stock (array|null).
     Lagerbezug lässt sich hier ergänzen — Portal-Rücksendungen kommen ohne Lager. --}}
<x-modal
    :id="'claim-rma-receive-' . $rma->id"
    :embedded="false"
    :title="__('Wareneingang buchen')"
    :eyebrow="__('Rückläufer (RMA)')"
    icon="move_to_inbox"
    tone="primary"
    :badge="$rma->rma_number"
    :action="route('claims.rma.receive', $rma)"
    method="POST"
    :submit-label="__('Wareneingang buchen')"
>
    <x-form-group :legend="__('claims.rma.group_receipt')" icon="move_to_inbox" tone="primary" cols="2">
        <x-select-field name="stock_state" :id="'claim-rma-receive-' . $rma->id . '-state'" :label="__('Quarantäne-Zustand')" required>
            @foreach (\App\Enums\Inventory\StockState::quarantine() as $state)
                <option value="{{ $state->value }}">{{ $state->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="condition_note" :id="'claim-rma-receive-' . $rma->id . '-condition'" :label="__('Zustand (optional)')" maxlength="4000" :value="$rma->condition_note" />
    </x-form-group>
    @if ($stock !== null)
        @include('claims._rma_stock_fields', ['claim' => $claim, 'rma' => $rma, 'prefix' => 'claim-rma-receive-' . $rma->id, 'stock' => $stock, 'warehouseRequired' => true])
    @else
        <p class="text-sm text-muted">{{ __('claims.rma.no_stock_module') }}</p>
    @endif
</x-modal>
