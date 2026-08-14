<x-modal
    :title="__('invoice-import.options_title')"
    :eyebrow="$invoice->number"
    icon="receipt_long"
    tone="primary"
    :action="route('invoices.einvoice-options.update', $invoice)"
    method="PATCH"
    :submit-label="__('Speichern')"
>
    <div class="space-y-4">
        <x-form-group :legend="__('invoice-import.group_invoice')" icon="description" tone="primary" cols="2">
            <x-input-field name="number" :label="__('invoice-import.invoice_number')" required maxlength="64" :value="old('number', $invoice->number)" />
            <x-select-field name="currency" :label="__('invoice-import.currency')" required>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->value }}" @selected(old('currency', $invoice->currency->value) === $currency->value)>{{ $currency->value }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="issued_on" type="date" :label="__('invoice-import.issue_date')" :value="old('issued_on', $invoice->issued_on?->toDateString())" />
            <x-input-field name="due_on" type="date" :label="__('invoice-import.due_date')" :value="old('due_on', $invoice->due_on?->toDateString())" />
        </x-form-group>

        <x-form-group :legend="__('invoice-import.group_einvoice')" icon="data_object" tone="info">
            <x-select-field name="delivery_format" :label="__('invoice-import.delivery_format')" required>
                @foreach ($formats as $format)
                    <option value="{{ $format->value }}" @selected(old('delivery_format', $invoice->delivery_format->value) === $format->value)>{{ $format->label() }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="buyer_reference" :label="__('invoice-import.buyer_reference')" maxlength="100"
                           :value="old('buyer_reference', $invoice->buyer_reference ?? $invoice->customer->buyer_reference)"
                           :hint="__('invoice-import.buyer_reference_hint')" />
        </x-form-group>

        <x-validation-errors />
    </div>
</x-modal>
