{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _financial_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: kaufmännische Folge vorschlagen (Feature 072, MVP-252/1577). Erwartet: $claim, $invoices,
     $localBilling (bool: Gutschrift und Storno entstehen hier und brauchen die Rechnung). --}}
<x-modal
    id="claim-financial-create"
    :embedded="false"
    :title="__('Folge vorschlagen')"
    :eyebrow="__('Kaufmännische Folgen')"
    icon="payments"
    tone="primary"
    :badge="$claim->number"
    :action="route('claims.financial.store', $claim)"
    method="POST"
    :submit-label="__('Folge vorschlagen')"
>
    <x-form-group :legend="__('Kaufmännische Folgen')" icon="payments" tone="primary" cols="2">
        <x-select-field name="kind" id="claim-financial-kind" :label="__('Art')" required>
            @foreach (\App\Enums\Claims\ClaimFinancialKind::cases() as $kind)
                <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="amount" id="claim-financial-amount" type="number" step="0.01" min="0" :label="__('Betrag')" />
        <x-select-field name="invoice_id" id="claim-financial-invoice" :label="__('Rechnung')" span="2"
                        :hint="$localBilling ? __('claims.financial.hint_invoice_local') : __('claims.financial.hint_invoice_external')">
            <option value="">—</option>
            @foreach ($invoices as $invoice)
                <option value="{{ $invoice->sqid }}" @selected($claim->invoice_id === $invoice->id)>{{ $invoice->number }}@if ($invoice->issued_on !== null) · {{ $invoice->issued_on->fdate() }}@endif · {{ $invoice->status->label() }}</option>
            @endforeach
        </x-select-field>
        <x-textarea-field name="justification" id="claim-financial-justification" :label="__('Pflichtbegründung (min. 10 Zeichen)')" rows="2" span="2" required minlength="10" maxlength="4000"></x-textarea-field>
    </x-form-group>
</x-modal>
