{{-- Variablen: $invoice (Invoice), $item (InvoiceItem|null) --}}
@php
    $isEdit = $item->exists ?? false;
    $action = $isEdit
        ? route('invoices.items.update', [$invoice, $item])
        : route('invoices.items.store', $invoice);
    $method = $isEdit ? 'PUT' : 'POST';
    $title = $isEdit ? __('Position bearbeiten') : __('Position hinzufügen');
@endphp
<x-modal
    :title="$title"
    :eyebrow="$invoice->number"
    icon="add"
    tone="primary"
    :action="$action"
    :method="$method"
    :submit-label="__('Speichern')"
    size="md">

    <x-form-group :legend="__('Position')" icon="receipt_long" tone="primary" cols="2">
        <div class="md:col-span-2">
            <label class="form-control w-full">
                <span class="label-text">{{ __('Beschreibung') }}</span>
                <input type="text" name="description" required maxlength="1000"
                       class="input input-bordered w-full"
                       value="{{ old('description', $item->description ?? '') }}">
            </label>
        </div>
        <label class="form-control w-full">
            <span class="label-text">{{ __('Menge') }}</span>
            <input type="number" name="quantity" required min="0" step="0.01"
                   class="input input-bordered w-full"
                   value="{{ old('quantity', (string) ($item->quantity ?? '1.00')) }}">
        </label>
        <label class="form-control w-full">
            <span class="label-text">{{ __('Einheit') }}</span>
            <input type="text" name="unit" maxlength="32"
                   class="input input-bordered w-full"
                   value="{{ old('unit', $item->unit ?? __('invoicing.unit_hour')) }}">
        </label>
        <label class="form-control w-full">
            <span class="label-text">{{ __('Einzelpreis') }} ({{ $invoice->currency }})</span>
            <input type="number" name="unit_price" required min="0" step="0.01"
                   class="input input-bordered w-full"
                   value="{{ old('unit_price', (string) ($item->unit_price ?? '0.00')) }}">
        </label>
        <label class="form-control w-full">
            <span class="label-text">{{ __('Position') }}</span>
            <input type="number" name="position" min="0" step="1"
                   class="input input-bordered w-full"
                   value="{{ old('position', (string) ($item->position ?? '')) }}">
        </label>
    </x-form-group>
</x-modal>
