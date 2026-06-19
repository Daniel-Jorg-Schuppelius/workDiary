{{-- Erwartet: $isDialog, $suppliers, $warehouses --}}
@php $isDialog = $isDialog ?? false; @endphp

<x-modal
    :title="__('procurement.action.create')"
    :eyebrow="__('procurement.title')"
    icon="shopping_cart"
    tone="primary"
    :action="route('purchase-orders.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Anlegen')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('purchase-orders.create') . '?dialog=1' }}">
    @endif

    <x-form-group :legend="__('Stammdaten')" icon="shopping_cart" tone="primary" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('procurement.field.supplier') }} *</label>
            <select name="supplier" class="select select-bordered w-full" required>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->sqid }}">{{ $supplier->name }}</option>
                @endforeach
            </select>
            @error('supplier')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('procurement.field.warehouse') }} *</label>
            <select name="warehouse" class="select select-bordered w-full" required>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->sqid }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
            @error('warehouse')<p class="text-error text-sm">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('procurement.field.expected_at') }}</label>
            <input name="expected_at" type="date" class="input input-bordered w-full" value="{{ old('expected_at') }}">
        </div>
        <div class="fieldset">
            <label class="fieldset-label">{{ __('procurement.field.note') }}</label>
            <input name="note" type="text" maxlength="2000" class="input input-bordered w-full" value="{{ old('note') }}">
        </div>
    </x-form-group>
</x-modal>
