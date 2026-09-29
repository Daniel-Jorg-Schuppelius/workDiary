{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _batch_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lizenzpaket anlegen (MVP-1024): so viele leere, nummerierte Lizenzen wie
  gekauft. Die Menge ist danach fest; Nachkäufe sind neue Pakete.
--}}
<x-modal
    :title="__('resale.license.action.new_batch')"
    :eyebrow="__('resale.license.title')"
    icon="inventory" tone="primary" size="lg"
    :action="route('finance.resale.licenses.batches.store')"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.license.action.create_batch')">
    <x-form-group :legend="__('resale.license.batch_legend')" icon="inventory" tone="primary" cols="2">
        <x-select-field name="product_id" :label="__('resale.license.field.product')" required>
            <option value="">—</option>
            @foreach ($products as $product)
                <option value="{{ $product->sqid }}" @selected(old('product_id', $productSqid) === $product->sqid)>{{ $product->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="reference" :label="__('resale.license.field.reference')" :value="old('reference')" required maxlength="80" :hint="__('resale.license.hint.reference')" />
        <x-input-field name="purchased_on" type="date" :label="__('resale.license.field.purchased_on')" :value="old('purchased_on', now()->toDateString())" required />
        <x-input-field name="quantity" type="number" min="1" :max="\App\Services\Reselling\License\LicenseStockService::MAX_BATCH_QUANTITY" :label="__('resale.license.field.quantity')" :value="old('quantity')" required :hint="__('resale.license.hint.quantity')" />
        <x-select-field name="supplier_id" :label="__('resale.license.field.supplier')">
            <option value="">—</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->sqid }}" @selected(old('supplier_id') === $supplier->sqid)>{{ $supplier->name }}</option>
            @endforeach
        </x-select-field>
        <x-input-field name="supplier_name" :label="__('resale.license.field.supplier_name')" :value="old('supplier_name')" maxlength="160" :hint="__('resale.license.hint.supplier_name')" />
        <x-select-field name="document" :label="__('resale.license.field.document')" span="2" :hint="__('resale.license.hint.document')">
            <option value="">—</option>
            @foreach ($documents as $document)
                <option value="{{ $document->key }}" @selected(old('document') === $document->key)>{{ __('resale.purchase_document.source.' . $document->sourceKey) }} · {{ $document->label() }}</option>
            @endforeach
        </x-select-field>
    </x-form-group>
    <x-textarea-field name="note" rows="2" :label="__('resale.license.field.note')" :value="old('note')" maxlength="2000" />
</x-modal>
