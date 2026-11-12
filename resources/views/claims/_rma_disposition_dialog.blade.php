{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _rma_disposition_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Verwendungsentscheidung eines Rückläufers (Feature 072, MVP-250/1576). Erwartet: $rma, $stock (array|null).
     Gebucht wird nur, was beim Wareneingang in die Quarantäne ging — der Lagerbezug ist hier fest. --}}
<x-modal
    :id="'claim-rma-disposition-' . $rma->id"
    :embedded="false"
    :title="__('Verwendung entscheiden')"
    :eyebrow="__('Rückläufer (RMA)')"
    icon="rule"
    tone="primary"
    :badge="$rma->rma_number"
    :action="route('claims.rma.disposition', $rma)"
    method="POST"
    :submit-label="__('Verwendung entscheiden')"
>
    <x-form-group :legend="__('Verwendung')" icon="rule" tone="primary" cols="1">
        <x-select-field name="disposition" :id="'claim-rma-disposition-' . $rma->id . '-kind'" :label="__('Verwendung')" required>
            @foreach (\App\Enums\Claims\ClaimRmaDisposition::cases() as $disp)
                <option value="{{ $disp->value }}">{{ $disp->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="disposition_note" :id="'claim-rma-disposition-' . $rma->id . '-note'" :label="__('validation.attributes.disposition_note')" rows="2" maxlength="4000">{{ $rma->disposition_note }}</x-textarea-field>
        <p class="text-sm text-muted">
            {{ $stock !== null && $rma->hasStockReference() ? __('claims.rma.disposition_books') : __('claims.rma.disposition_documents') }}
        </p>
    </x-form-group>
</x-modal>
